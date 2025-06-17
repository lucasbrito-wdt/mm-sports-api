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
            '%s/%s/%s/%s',
            $frontEndAbsoluteDir,
            'pages',
            Str::snake($domain, '-'),
            'cadastrar'
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
        $storeName = 'use' . $modelName . 'Store';

        // Construir interface name
        $interfaceName = "I" . $modelName;

        // Construir entity singular var
        $entitySingularVar = strtolower(Str::singular($domain));

        // Construir form name
        $formName = Str::singular($domain) . 'Form';

        return [
            '{{store_name}}' => $storeName,
            '{{interface_name}}' => $interfaceName,
            '{{entity_var}}' => $entitySingularVar,
            '{{form}}' => $formName,
        ];
    }
}
