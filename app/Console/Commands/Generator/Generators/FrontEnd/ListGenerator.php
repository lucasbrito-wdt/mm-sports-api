<?php

namespace App\Console\Commands\Generator\Generators\FrontEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use Coduo\PHPHumanizer\StringHumanizer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ListGenerator
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
        $schema = $config['schema'];

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

        // Nome do arquivo seguindo padrão antigo
        $fileName = 'index.vue';
        $filePath = $fullPath . '/' . $fileName;

        // Verificar se o arquivo já existe
        if (File::exists($filePath) && !($config['force'] ?? false)) {
            return false;
        }

        // Construir variáveis para o template
        $templateVars = $this->buildTemplateVariables($config);

        // Usar stub antiga
        $listContent = $this->templateManager->processStub(
            'FrontEnd/listar.stub',
            $templateVars
        );

        // Salvar o arquivo
        File::put($filePath, $listContent);

        return true;
    }

    private function buildTemplateVariables(array $config): array
    {
        $modelName = $config['model'];
        $domain = $config['domain'];
        $schema = $config['schema'];

        // Construir store name
        $storeName = 'use' . Str::studly($modelName) . 'Store';
        $storeVar = Str::camel($modelName) . 'Store';

        // Construir interface name
        $interfaceName = "I$modelName";

        // Construir entity singular var
        $entitySingularVar = Str::kebab($modelName);
        $entityLabel = StringHumanizer::humanize($modelName);

        // Construir colunas da tabela
        $columns = $this->buildTableColumns($schema);

        // Construir filtros
        $filters = $this->buildFilters($schema);

        return [
            '{{list_title}}' => StringHumanizer::humanize(str($domain)->plural()),
            '{{list_subtitle}}' => "Gerencie os " . strtolower(StringHumanizer::humanize(str($domain)->plural())),
            '{{store_name}}' => $storeName,
            '{{store_var}}' => $storeVar,
            '{{interface_name}}' => $interfaceName,
            '{{entity_singular_var}}' => $entitySingularVar,
            '{{entity_label}}' => $entityLabel,
            '{{columns}}' => $columns,
            '{{filters}}' => $filters,
            '{{custom_cells}}' => '',
        ];
    }

    /**
     * Constrói colunas da tabela (SimpleColumn[])
     */
    private function buildTableColumns(string $schema): string
    {
        $columns = [];
        $columnDefs = explode(';', rtrim($schema, ';'));

        foreach ($columnDefs as $column) {
            @[$field, $params] = explode('=', $column);

            if (!$field) {
                continue;
            }

            // Ignorar campos como timestamps, password, etc.
            if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'])) {
                continue;
            }

            $label = StringHumanizer::humanize($field);
            $columns[] = "  { key: '{$field}', label: '{$label}', sortable: true }";
        }

        // Adicionar coluna de data de criação se não existir
        if (!str_contains($schema, 'created_at')) {
            $columns[] = "  { key: 'created_at', label: 'Data de Criação', sortable: true, width: '180px' }";
        }

        return implode(",\n", $columns);
    }

    /**
     * Constrói filtros (FilterConfig[])
     */
    private function buildFilters(string $schema): string
    {
        $filters = [];
        
        // Filtro de busca padrão
        $filters[] = "  {\n    type: 'search',\n    id: 'search',\n    placeholder: 'Buscar...',\n    defaultValue: '',\n    colSpan: 2,\n  }";

        return implode(",\n", $filters);
    }
}
