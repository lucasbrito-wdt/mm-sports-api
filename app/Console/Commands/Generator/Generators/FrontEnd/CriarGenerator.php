<?php

namespace App\Console\Commands\Generator\Generators\FrontEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CriarGenerator
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

        // Nome do arquivo: create.vue
        $fileName = 'create.vue';
        $filePath = $fullPath . '/' . $fileName;

        // Verificar se o arquivo já existe
        if (File::exists($filePath) && !($config['force'] ?? false)) {
            return false;
        }

        // Construir variáveis para o template
        $templateVars = $this->buildTemplateVariables($config);

        // Usar stub antiga
        $criarContent = $this->templateManager->processStub(
            'FrontEnd/cadastrar.stub',
            $templateVars
        );

        // Salvar o arquivo
        File::put($filePath, $criarContent);

        return true;
    }

    private function buildTemplateVariables(array $config): array
    {
        $modelName = $config['model'];
        $domain = $config['domain'];

        // Construir store name
        $storeName = 'use' . Str::studly($modelName) . 'Store';
        $storeVar = Str::camel($modelName) . 'Store';

        // Construir entity singular var
        $entitySingularVar = Str::kebab($modelName);
        $entityName = Str::studly($modelName);
        $entityLabel = ucfirst(Str::snake($modelName, ' '));

        // Construir form component name
        $formComponent = Str::studly($modelName) . 'Form';

        // Construir schema name
        $schemaName = Str::camel($modelName) . 'CreateSchema';

        return [
            '{{store_name}}' => $storeName,
            '{{store_var}}' => $storeVar,
            '{{entity_singular_var}}' => $entitySingularVar,
            '{{entity_name}}' => $entityName,
            '{{entity_label}}' => $entityLabel,
            '{{form_component}}' => $formComponent,
            '{{schema_name}}' => $schemaName,
            '{{create_subtitle}}' => "Preencha os dados para criar um novo {$entityLabel}",
        ];
    }
}
