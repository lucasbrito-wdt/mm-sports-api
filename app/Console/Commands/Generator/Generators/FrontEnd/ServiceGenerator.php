<?php

namespace App\Console\Commands\Generator\Generators\FrontEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ServiceGenerator
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
        $foreignKeys = $config['foreignKeys'] ?? [];

        $frontEndAbsoluteDir = $this->getFrontendPath();

        $fullPath = sprintf(
            '%s/%s',
            $frontEndAbsoluteDir,
            'services'
        );

        // Criar diretório se não existir
        if (!File::exists($fullPath)) {
            File::makeDirectory($fullPath, 0755, true);
        }

        // Nome do arquivo: {entity}.service.ts (ex: users.service.ts)
        $fileName = Str::kebab($modelName) . '.service.ts';
        $filePath = $fullPath . '/' . $fileName;
        
        // Nome do service: {Entity}Service (ex: UsersService)
        $serviceName = Str::studly($modelName) . 'Service';

        // Verificar se o arquivo já existe
        if (File::exists($filePath) && !($config['force'] ?? false)) {
            return false;
        }

        // Construir variáveis para o template
        $templateVars = $this->buildTemplateVariables($config, $serviceName);

        // Usar stub antiga
        $serviceContent = $this->templateManager->processStub(
            'FrontEnd/service.stub',
            $templateVars
        );

        // Salvar o arquivo
        File::put($filePath, $serviceContent);

        return true;
    }

    private function buildTemplateVariables(array $config, string $serviceName): array
    {
        $modelName = $config['model'];
        $domain = $config['domain'];
        $foreignKeys = $config['foreignKeys'] ?? [];

        // Construir endpoint
        $endpoint = Str::kebab(Str::plural($modelName));

        // Construir métodos FK
        $methodsFk = $this->buildForeignKeyMethods($foreignKeys);

        return [
            '{{service_name}}' => $serviceName,
            '{{end_point}}' => $endpoint,
            '{{methods_fk}}' => implode("\n", $methodsFk),
        ];
    }

    private function buildForeignKeyMethods(array $foreignKeys): array
    {
        if (empty($foreignKeys)) {
            return [];
        }

        $methods = [];
        foreach ($foreignKeys as $fk) {
            $pluralName = Str::plural(strtolower($fk['model']));
            $methodName = 'getAll' . Str::plural($fk['model']);

            $methods[] = "  async {$methodName}() {";
            $methods[] = "    return this.get('/" . Str::kebab($fk['domain']) . "/" . Str::kebab($pluralName) . "')";
            $methods[] = "  }";
            $methods[] = "";
        }
        return $methods;
    }
}
