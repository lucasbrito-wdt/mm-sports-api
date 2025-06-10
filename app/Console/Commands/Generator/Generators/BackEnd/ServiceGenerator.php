<?php

namespace App\Console\Commands\Generator\Generators\BackEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use App\Console\Commands\Generator\Utils\RouteManager;
use Illuminate\Support\Facades\File;

class ServiceGenerator
{
    private TemplateManager $templateManager;
    private RouteManager $routeManager;

    public function __construct(TemplateManager $templateManager, RouteManager $routeManager)
    {
        $this->templateManager = $templateManager;
        $this->routeManager = $routeManager;
    }

    public function generate(array $config): bool
    {
        $modelName = $config['model'];
        $domain = $config['domain'];
        $serviceName = $config['service'] ?? "{$modelName}Service";

        // Gerar métodos FK se existirem
        $fkMethods = $this->generateFKMethods($config);

        // Gerar conteúdo do service
        $serviceContent = $this->templateManager->processStub(
            'BackEnd/service.stub',
            [
                '{{namespace}}' => "App\\Domains\\{$domain}\\Services",
                '{{serviceName}}' => $serviceName,
                '{{modelName}}' => $modelName,
                '{{modelNamespace}}' => "App\\Domains\\{$domain}\\Models\\{$modelName}",
                '{{modelVariable}}' => lcfirst($modelName),
                '{{methodsForeign}}' => $fkMethods,
            ]
        );

        // Caminho do diretório de serviços
        $serviceDir = app_path("Domains/{$domain}/Services");

        // Criar diretório se não existir
        if (!File::exists($serviceDir)) {
            File::makeDirectory($serviceDir, 0755, true);
        }

        // Salvar o arquivo
        $servicePath = "{$serviceDir}/{$serviceName}.php";
        File::put($servicePath, $serviceContent);

        return true;
    }

    /**
     * Gera métodos FK para o service.
     *
     * @param array $config Configuração do gerador
     * @return string Código dos métodos FK
     */
    private function generateFKMethods(array $config): string
    {
        if (empty($config['foreignKeys'])) {
            return '';
        }

        $methods = [];

        foreach ($config['foreignKeys'] as $fk) {
            $namespaceFk = "\\App\\Domains\\{$fk['domain']}\\Models\\{$fk['model']}";
            $methods[] = $this->routeManager->createFKServiceContent(
                $fk['model'],
                $namespaceFk
            );
        }

        return implode("\n\n\t", $methods);
    }
}
