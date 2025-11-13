<?php

namespace App\Console\Commands\Generator\Generators\BackEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use App\Console\Commands\Generator\Utils\RouteManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ControllerGenerator
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
        $controllerName = "{$modelName}Controller";

        // Gerar request store e update
        $this->generateRequests($config);

        // Gerar métodos FK se existirem
        $fkMethods = $this->generateFKMethods($config);

        // Gerar conteúdo do controller
        $controllerContent = $this->templateManager->processStub(
            'BackEnd/controller.stub',
            [
                '{{namespace}}' => "App\\Domains\\{$domain}\\Controllers",
                '{{controllerName}}' => $controllerName,
                '{{modelName}}' => $modelName,
                '{{serviceName}}' => $serviceName,
                '{{serviceNamespace}}' => "App\\Domains\\{$domain}\\Services\\{$serviceName}",
                '{{modelVariable}}' => lcfirst($modelName),
                '{{requestName}}' => "{$modelName}Request",
                '{{requestNamespace}}' => "App\\Domains\\{$domain}\\Requests\\{$modelName}Request",
                '{{domainName}}' => $domain,
                '{{subjectName}}' => str($domain)->lower(),
                '{{methodsForeign}}' => $fkMethods,
            ]
        );

        // Caminho do diretório de controllers
        $controllerDir = app_path("Domains/{$domain}/Controllers");

        // Criar diretório se não existir
        if (!File::exists($controllerDir)) {
            File::makeDirectory($controllerDir, 0755, true);
        }

        // Salvar o arquivo
        $controllerPath = "{$controllerDir}/{$controllerName}.php";
        File::put($controllerPath, $controllerContent);

        return true;
    }

    private function generateRequests(array $config): void
    {
        $modelName = $config['model'];
        $domain = $config['domain'];

        // Diretório de requests
        $requestsDir = app_path("Domains/{$domain}/Requests");

        // Criar diretório se não existir
        if (!File::exists($requestsDir)) {
            File::makeDirectory($requestsDir, 0755, true);
        }

        // Preparar regras de validação
        $validationRules = $this->buildValidationRules($config);

        // Gerar request de store
        $storeRequestContent = $this->templateManager->processStub(
            'BackEnd/request.stub',
            [
                '{{namespace}}' => "App\\Domains\\{$domain}\\Requests",
                '{{requestName}}' => "{$modelName}Request",
                '{{validationRules}}' => $validationRules,
            ]
        );

        // Salvar arquivo de store request
        File::put("{$requestsDir}/{$modelName}Request.php", $storeRequestContent);
    }

    private function buildValidationRules(array $config): string
    {
        $rules = [];
        $columns = explode(';', rtrim($config['schema'], ';'));

        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column);

            if (!$field || !$params) {
                continue;
            }

            // Separar parâmetros por vírgula
            $paramParts = explode(',', $params);
            $type = trim($paramParts[0]);

            if (!$type) {
                continue;
            }

            // Inicializar variáveis
            $required = false;
            $option1 = null;
            $option2 = null;
            $enumValues = [];

            // Processar parâmetros
            for ($i = 1; $i < count($paramParts); $i++) {
                $part = trim($paramParts[$i]);

                // Verificar se é 'req'
                if (strtolower($part) === 'req') {
                    $required = true;
                    continue;
                }

                // Verificar se contém valores de enum (com |)
                if (str_contains($part, '|')) {
                    $enumValues = array_map('trim', explode('|', $part));
                    continue;
                }

                // Caso contrário, é uma opção
                if (!$option1) {
                    $option1 = $part;
                } elseif (!$option2) {
                    $option2 = $part;
                }
            }

            $ruleArray = [];

            // Verificar se é obrigatório
            if ($required) {
                $ruleArray[] = 'required';
            } else {
                $ruleArray[] = 'nullable';
            }

            // Adicionar regras com base no tipo
            switch (strtolower($type)) {
                case 'string':
                    $ruleArray[] = 'string';
                    $length = $option1 ?: 255;
                    $ruleArray[] = "max:{$length}";
                    break;

                case 'text':
                    $ruleArray[] = 'string';
                    // Só adiciona max se option1 for um número válido
                    if ($option1 && is_numeric($option1)) {
                        $ruleArray[] = "max:{$option1}";
                    }
                    break;

                case 'integer':
                case 'biginteger':
                    $ruleArray[] = 'integer';
                    break;

                case 'boolean':
                    $ruleArray[] = 'boolean';
                    break;

                case 'date':
                case 'datetime':
                    $ruleArray[] = 'date';
                    break;

                case 'decimal':
                case 'float':
                    $ruleArray[] = 'numeric';
                    break;

                case 'json':
                    $ruleArray[] = 'json';
                    break;

                case 'enum':
                    $options = !empty($enumValues) ? $enumValues : explode('|', $option1);
                    $ruleArray[] = 'in:' . implode(',', $options);
                    break;

                case 'email':
                    $ruleArray[] = 'email';
                    break;
            }

            $rules[] = "'{$field}' => [" . implode(', ', array_map(fn($rule) => "'{$rule}'", $ruleArray)) . "],";
        }

        // Adicionar regras para chaves estrangeiras
        if (!empty($config['foreignKeys'])) {
            foreach ($config['foreignKeys'] as $fk) {
                if ($fk['relation'] === 'belongsTo' || $fk['relation'] === 'hasMany' || $fk['relation'] === 'hasOne') {
                    $foreignKey = Str::snake($fk['model']) . '_id';
                    $ruleArray = [];

                    if ($fk['required']) {
                        $ruleArray[] = 'required';
                    } else {
                        $ruleArray[] = 'nullable';
                    }

                    $ruleArray[] = 'ulid';
                    $ruleArray[] = 'exists:' . Str::snake(Str::plural($fk['model'])) . ',id';

                    $rules[] = "'{$foreignKey}' => [" . implode(', ', array_map(fn($rule) => "'{$rule}'", $ruleArray)) . "],";
                }
            }
        }

        return implode("\n            ", $rules);
    }

    /**
     * Gera métodos FK para o controller.
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
            $serviceName = "{$fk['model']}Service";
            $methods[] = $this->routeManager->createFKControllerContent(
                $fk['model'],
                $serviceName
            );
        }

        return implode("\n\n\t", $methods);
    }
}
