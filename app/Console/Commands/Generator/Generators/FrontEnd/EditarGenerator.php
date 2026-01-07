<?php

namespace App\Console\Commands\Generator\Generators\FrontEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EditarGenerator
{
    use FrontendPathTrait;

    private TemplateManager $templateManager;

    public function __construct(TemplateManager $templateManager)
    {
        $this->templateManager = $templateManager;
    }

    public function generate(array $config): bool
    {
        $modelName = $config['model'];
        $domain = $config['domain'];

        $frontEndAbsoluteDir = $this->getFrontendPath();

        $fullPath = sprintf(
            '%s/%s/%s',
            $frontEndAbsoluteDir,
            'pages',
            Str::kebab($modelName)
        );

        // Criar diretório se não existir
        if (!File::exists($fullPath)) {
            File::makeDirectory($fullPath, 0755, true);
        }

        // Criar subdiretório [id] se não existir
        $idPath = $fullPath . '/[id]';
        if (!File::exists($idPath)) {
            File::makeDirectory($idPath, 0755, true);
        }

        // Nome do arquivo: [id]/edit.vue
        $fileName = 'edit.vue';
        $filePath = $idPath . '/' . $fileName;

        // Verificar se o arquivo já existe
        if (File::exists($filePath) && !($config['force'] ?? false)) {
            return false;
        }

        // Construir variáveis para o template
        $templateVars = $this->buildTemplateVariables($config);

        // Usar stub antiga
        $editarContent = $this->templateManager->processStub(
            'FrontEnd/editar.stub',
            $templateVars
        );

        // Salvar o arquivo
        File::put($filePath, $editarContent);

        return true;
    }

    private function buildTemplateVariables(array $config): array
    {
        $modelName = $config['model'];
        $domain = $config['domain'];
        $schema = $config['schema'] ?? '';

        // Construir store name
        $storeName = 'use' . Str::studly($modelName) . 'Store';
        $storeVar = Str::camel($modelName) . 'Store';

        // Construir entity singular var
        $entitySingularVar = Str::kebab($modelName);
        $entityId = Str::camel($modelName) . 'Id';
        $entityName = Str::studly($modelName);
        $entityLabel = ucfirst(Str::snake($modelName, ' '));

        // Construir form component name
        $formComponent = Str::studly($modelName) . 'Form';

        // Construir schema name
        $updateSchemaName = Str::camel($modelName) . 'UpdateSchema';

        // Construir valores padrão e mapeados
        $defaultValues = $this->buildDefaultValues($schema);
        $mappedValues = $this->buildMappedValues($schema);

        return [
            '{{store_name}}' => $storeName,
            '{{store_var}}' => $storeVar,
            '{{entity_singular_var}}' => $entitySingularVar,
            '{{entity_id}}' => $entityId,
            '{{entity_name}}' => $entityName,
            '{{entity_label}}' => $entityLabel,
            '{{form_component}}' => $formComponent,
            '{{update_schema_name}}' => $updateSchemaName,
            '{{default_values}}' => $defaultValues,
            '{{mapped_values}}' => $mappedValues,
            '{{edit_subtitle}}' => "Atualize os dados do {$entityLabel}",
        ];
    }

    /**
     * Constrói valores padrão para initialValues
     */
    private function buildDefaultValues(string $schema): string
    {
        $values = [];
        $columns = explode(';', rtrim($schema, ';'));

        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column);
            if (!$field || in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            @[$type] = explode(',', $params ?? '');
            $defaultValue = $this->getDefaultValueForType($type);
            $values[] = "    {$field}: {$defaultValue}";
        }

        return "{\n" . implode(",\n", $values) . "\n  }";
    }

    /**
     * Constrói valores mapeados para initialValues
     */
    private function buildMappedValues(string $schema): string
    {
        $values = [];
        $columns = explode(';', rtrim($schema, ';'));

        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column);
            if (!$field || in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            $values[] = "    {$field}: data.{$field} || ''";
        }

        return "{\n" . implode(",\n", $values) . "\n  }";
    }

    /**
     * Obtém valor padrão baseado no tipo
     */
    private function getDefaultValueForType(string $type): string
    {
        return match (strtolower($type)) {
            'integer', 'biginteger', 'decimal', 'float' => "''",
            'boolean' => 'false',
            'date', 'datetime' => "''",
            default => "''",
        };
    }
}
