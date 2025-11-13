<?php

namespace App\Console\Commands\Generator\Utils;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RouteManager
{
    /**
     * Diretório base para os arquivos de rotas
     */
    const ROUTES_BASE_PATH = 'routes';

    /**
     * Cria automaticamente as rotas para um domínio gerado.
     *
     * @param  string  $domainName  Nome do domínio
     * @param  string  $modelName  Nome do modelo/controller
     * @param  array  $options  Opções adicionais (middleware, prefix, etc.)
     */
    public function createDomainRoutes(string $domainName, string $modelName, array $options = []): bool
    {
        try {
            // Verifica e cria estrutura de diretórios de rotas se necessário
            $this->ensureRoutesStructure();

            // Gera o arquivo de rotas para o domínio
            $routeFilePath = base_path(self::ROUTES_BASE_PATH . '/domains/' . Str::kebab($domainName) . '.php');

            // Se o arquivo não existe, cria novo
            if (! File::exists($routeFilePath)) {
                $this->createNewDomainRouteFile($domainName, $modelName, $routeFilePath, $options);
            } else {
                // Se existe, adiciona as rotas do novo modelo
                $this->addRoutesToExistingFile($domainName, $modelName, $routeFilePath, $options);
            }

            // Atualiza o arquivo principal de rotas se necessário
            $this->updateMainRoutesFile($domainName);

            return true;
        } catch (\Exception $e) {
            // Log do erro para debug
            Log::error('Erro ao gerar rotas para domínio: ' . $domainName, [
                'model' => $modelName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Remove as rotas de um domínio (usado no rollback).
     *
     * @param  string  $domainName  Nome do domínio
     * @param  string|null  $modelName  Nome do modelo (opcional, se não informado remove o arquivo inteiro)
     */
    public function removeDomainRoutes(string $domainName, ?string $modelName = null): bool
    {
        try {
            // Gera o caminho do arquivo de rotas
            $routeFilePath = base_path(self::ROUTES_BASE_PATH . '/domains/' . Str::kebab($domainName) . '.php');

            if (! File::exists($routeFilePath)) {
                return true; // Arquivo não existe, nada para remover
            }

            if ($modelName === null) {
                // Remove o arquivo inteiro
                File::delete($routeFilePath);

                // Remove a referência do arquivo principal
                $this->removeFromMainRoutesFile($domainName);
            } else {
                // Remove apenas as rotas do modelo específico
                $this->removeModelRoutesFromFile($routeFilePath, $modelName);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verifica e cria a estrutura de diretórios de rotas.
     */
    protected function ensureRoutesStructure(): void
    {
        $routesPath = base_path(self::ROUTES_BASE_PATH);
        $domainsPath = $routesPath . '/domains';

        // Cria diretório routes se não existir
        if (! File::exists($routesPath)) {
            File::makeDirectory($routesPath, 0755, true);
        }

        // Cria diretório domains dentro de routes
        if (! File::exists($domainsPath)) {
            File::makeDirectory($domainsPath, 0755, true);
        }
    }

    /**
     * Cria um novo arquivo de rotas para o domínio.
     *
     * @param  string  $domainName  Nome do domínio
     * @param  string  $modelName  Nome do modelo
     * @param  string  $filePath  Caminho do arquivo a ser criado
     * @param  array  $options  Opções adicionais
     */
    protected function createNewDomainRouteFile(string $domainName, string $modelName, string $filePath, array $options = []): void
    {
        $prefix = $options['prefix'] ?? Str::kebab($domainName);
        $middleware = $options['middleware'] ?? ['auth:api'];
        $controllerNamespace = "App\\Domains\\{$domainName}\\Controllers";
        $controllerName = "{$modelName}Controller";
        $foreignKeys = $options['foreignKeys'] ?? [];

        $middlewareString = "'" . implode("', '", $middleware) . "'";

        // Gerar rotas FK se existirem
        $fkRoutes = '';
        if (! empty($foreignKeys)) {
            foreach ($foreignKeys as $fk) {
                $fkRoutes .= "\n    " . $this->createFKRoutes($domainName, $modelName, $fk['model'], $controllerName);
            }
        }

        $content = "<?php

use {$controllerNamespace}\\{$controllerName};
use Illuminate\\Support\\Facades\\Route;

/*
|--------------------------------------------------------------------------
| {$domainName} Domain Routes
|--------------------------------------------------------------------------
|
| Rotas para o domínio {$domainName}
|
*/

Route::group([
    'middleware' => [{$middlewareString}],
    'as' => '" . Str::kebab($modelName) . "'
], function () {

    // {$modelName} Routes
    Route::apiResource('" . Str::kebab(Str::plural($modelName)) . "', {$controllerName}::class);
    Route::post('" . Str::kebab(Str::plural($modelName)) . "/search', [{$controllerName}::class, 'search']);
    {$fkRoutes}
});
";

        File::put($filePath, $content);
    }

    /**
     * Adiciona rotas de um novo modelo a um arquivo de domínio existente.
     *
     * @param  string  $domainName  Nome do domínio
     * @param  string  $modelName  Nome do modelo
     * @param  string  $filePath  Caminho do arquivo
     * @param  array  $options  Opções adicionais
     */
    protected function addRoutesToExistingFile(string $domainName, string $modelName, string $filePath, array $options = []): void
    {
        $content = File::get($filePath);
        $controllerNamespace = "App\\Domains\\{$domainName}\\Controllers";
        $controllerName = "{$modelName}Controller";
        $foreignKeys = $options['foreignKeys'] ?? [];

        // Verificar se as rotas do modelo já existem
        $routePattern = "Route::apiResource('" . Str::kebab(Str::plural($modelName)) . "', {$controllerName}::class)";
        if (str_contains($content, $routePattern)) {
            // Rotas já existem, não adicionar novamente
            return;
        }

        // Adiciona o use statement se não existir
        if (! str_contains($content, "use {$controllerNamespace}\\{$controllerName};")) {
            $useStatement = "use {$controllerNamespace}\\{$controllerName};";
            $content = str_replace(
                'use Illuminate\\Support\\Facades\\Route;',
                "use {$controllerNamespace}\\{$controllerName};\nuse Illuminate\\Support\\Facades\\Route;",
                $content
            );
        }

        // Gerar rotas FK se existirem
        $fkRoutes = '';
        if (! empty($foreignKeys)) {
            foreach ($foreignKeys as $fk) {
                $fkRoutes .= "\n    " . $this->createFKRoutes($domainName, $modelName, $fk['model'], $controllerName);
            }
        }

        // Adiciona as rotas antes do fechamento do grupo
        $newRoutes = "
    // {$modelName} Routes
    Route::apiResource('" . Str::kebab(Str::plural($modelName)) . "', {$controllerName}::class);{$fkRoutes}
    ";

        $content = str_replace('});', $newRoutes . '});', $content);

        File::put($filePath, $content);
    }

    /**
     * Atualiza o arquivo principal de rotas (api.php ou web.php) para incluir o domínio.
     *
     * @param  string  $domainName  Nome do domínio
     */
    protected function updateMainRoutesFile(string $domainName): void
    {
        $apiRoutesPath = base_path('routes/api.php');

        if (! File::exists($apiRoutesPath)) {
            return;
        }

        $content = File::get($apiRoutesPath);
        $domainRoute = "require __DIR__.'/domains/" . Str::kebab($domainName) . ".php';";

        // Verifica se a inclusão já existe
        if (str_contains($content, $domainRoute)) {
            return;
        }

        // Adiciona no final do arquivo
        $content = rtrim($content) . "\n\n// {$domainName} Domain Routes\n{$domainRoute}\n";

        File::put($apiRoutesPath, $content);
    }

    /**
     * Remove referência do domínio do arquivo principal de rotas.
     *
     * @param  string  $domainName  Nome do domínio
     */
    protected function removeFromMainRoutesFile(string $domainName): void
    {
        $apiRoutesPath = base_path('routes/api.php');

        if (! File::exists($apiRoutesPath)) {
            return;
        }

        $content = File::get($apiRoutesPath);
        $domainRoute = "require __DIR__.'/domains/" . Str::kebab($domainName) . ".php';";

        // Remove a linha de inclusão e o comentário
        $pattern = "/\n\/\/ {$domainName} Domain Routes\n{$domainRoute}/";
        $content = preg_replace($pattern, '', $content);

        File::put($apiRoutesPath, $content);
    }

    /**
     * Remove rotas de um modelo específico de um arquivo de domínio.
     *
     * @param  string  $filePath  Caminho do arquivo
     * @param  string  $modelName  Nome do modelo
     */
    protected function removeModelRoutesFromFile(string $filePath, string $modelName): void
    {
        $content = File::get($filePath);
        $controllerName = "{$modelName}Controller";

        // Remove o use statement
        $pattern = "/use App\\\\Domains\\\\.*\\\\Controllers\\\\{$controllerName};\n?/";
        $content = preg_replace($pattern, '', $content);

        // Remove o bloco de rotas do modelo
        $routePattern = "/\s*\/\/ {$modelName} Routes.*?Route::apiResource\(.*?{$controllerName}::class\);\s*\n/s";
        $content = preg_replace($routePattern, '', $content);

        File::put($filePath, $content);
    }

    /**
     * Verifica se as rotas de um domínio existem.
     *
     * @param  string  $domainName  Nome do domínio
     */
    public function domainRoutesExist(string $domainName): bool
    {
        $routeFilePath = base_path(self::ROUTES_BASE_PATH . '/domains/' . Str::kebab($domainName) . '.php');

        return File::exists($routeFilePath);
    }

    /**
     * Lista todos os arquivos de rotas de domínios existentes.
     *
     * @return array Array com nomes dos domínios que possuem arquivos de rotas
     */
    public function listDomainRoutes(): array
    {
        $domainsPath = base_path(self::ROUTES_BASE_PATH . '/domains');

        if (! File::exists($domainsPath)) {
            return [];
        }

        $files = File::files($domainsPath);
        $domains = [];

        foreach ($files as $file) {
            $filename = $file->getFilenameWithoutExtension();
            $domains[] = Str::studly(str_replace('-', '', $filename));
        }

        return $domains;
    }

    /**
     * Gera rotas com middleware customizado.
     *
     * @param  string  $domainName  Nome do domínio
     * @param  array  $middlewares  Array de middlewares
     * @return string Template das rotas com middleware
     */
    public function generateRoutesWithMiddleware(string $domainName, array $middlewares): string
    {
        $middlewareString = "'" . implode("', '", $middlewares) . "'";
        $prefix = Str::kebab($domainName);

        return "
Route::group([
    'prefix' => '{$prefix}',
    'middleware' => [{$middlewareString}],
], function () {
    // Routes will be added here
});
";
    }

    /**
     * Adiciona rotas de permissão para um domínio.
     *
     * @param  string  $domainName  Nome do domínio
     * @param  string  $modelName  Nome do modelo
     * @param  string  $filePath  Caminho do arquivo de rotas
     */
    public function addPermissionRoutes(string $domainName, string $modelName, string $filePath): void
    {
        $content = File::get($filePath);
        $controllerName = "{$modelName}Controller";

        $permissionRoutes = "
    // Permission-based routes for {$modelName}
    Route::middleware('can:view,{$modelName}')->group(function () {
        Route::get('" . Str::kebab(Str::plural($modelName)) . "', [{$controllerName}::class, 'index']);
        Route::get('" . Str::kebab(Str::plural($modelName)) . "/{id}', [{$controllerName}::class, 'show']);
    });

    Route::middleware('can:create,{$modelName}')->group(function () {
        Route::post('" . Str::kebab(Str::plural($modelName)) . "', [{$controllerName}::class, 'store']);
    });

    Route::middleware('can:update,{$modelName}')->group(function () {
        Route::put('" . Str::kebab(Str::plural($modelName)) . "/{id}', [{$controllerName}::class, 'update']);
    });

    Route::middleware('can:delete,{$modelName}')->group(function () {
        Route::delete('" . Str::kebab(Str::plural($modelName)) . "/{id}', [{$controllerName}::class, 'destroy']);
    });
    ";

        $content = str_replace('});', $permissionRoutes . '});', $content);

        File::put($filePath, $content);
    }

    /**
     * Adiciona rotas para um modelo a um arquivo de domínio existente.
     *
     * @param  array  $config  Configuração do modelo
     */
    public function addRoutes(array $config): bool
    {
        $domainName = $config['domain'];
        $modelName = $config['model'];
        $routeFilePath = base_path(self::ROUTES_BASE_PATH . '/domains/' . Str::kebab($domainName) . '.php');

        if (! File::exists($routeFilePath)) {
            // Se o arquivo não existe, cria um novo
            return $this->createDomainRoutes($domainName, $modelName, $config);
        }

        // Se existe, adiciona as rotas ao arquivo existente
        $this->addRoutesToExistingFile($domainName, $modelName, $routeFilePath, $config);

        return true;
    }

    /**
     * Cria rotas para Foreign Keys (FK).
     *
     * @param  string  $domainName  Nome do domínio
     * @param  string  $modelName  Nome do modelo atual
     * @param  string  $fkName  Nome da FK (modelo relacionado)
     * @param  string  $controllerName  Nome completo do controller
     * @return string Código da rota FK
     */
    public function createFKRoutes(string $domainName, string $modelName, string $fkName, string $controllerName): string
    {
        $modelNamePlural = Str::kebab(Str::plural($modelName));
        $fkNameCamel = Str::camel("listar{$fkName}");
        $fkNameSlug = Str::kebab($fkName);
        $fkRouteTemplate = "Route::get('%s/listar/%s', [%s::class, '%s']);";

        return sprintf($fkRouteTemplate, $modelNamePlural, $fkNameSlug, $controllerName, $fkNameCamel);
    }

    /**
     * Cria conteúdo de método de controller para Foreign Keys (FK).
     *
     * @param  string  $fkName  Nome da FK (modelo relacionado)
     * @return string Código do método do controller
     */
    public function createFKControllerContent(string $fkName): string
    {
        $methodName = Str::camel("listar{$fkName}");

        return "public function {$methodName}(Request \$request) {\n\t\t\$options = \$request->all();\n\t\treturn \$this->service->{$methodName}(\$options);\n\t}";
    }

    /**
     * Cria conteúdo de método de service para Foreign Keys (FK).
     *
     * @param  string  $fkName  Nome da FK (modelo relacionado)
     * @param  string  $namespaceFk  Namespace completo do modelo FK
     * @return string Código do método do service
     */
    public function createFKServiceContent(string $fkName, string $namespaceFk): string
    {
        $methodName = Str::camel("listar{$fkName}");

        return "public function {$methodName}(\$options) {\n\t\t\$data = {$namespaceFk}::query()->paginate(\$options['per_page'] ?? 15);\n\t\treturn \$data->items();\n\t}";
    }
}
