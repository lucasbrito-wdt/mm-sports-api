<?php

namespace App\Console\Commands\Generator\Utils;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IntegrityValidator
{
    private Command $command;
    private string $frontendPath;

    public function __construct(Command $command)
    {
        $this->command = $command;
        $this->setFrontendPath();
    }

    /**
     * Define o caminho do frontend
     */
    private function setFrontendPath(): void
    {
        $possiblePaths = [
            base_path('../frontend'),
            base_path('../vue-frontend'),
            base_path('../nuxt-frontend'),
            base_path('resources/frontend'),
            base_path('frontend')
        ];

        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                $this->frontendPath = $path;
                return;
            }
        }

        $this->frontendPath = base_path('../frontend');
    }

    /**
     * Valida integridade completa do projeto após rollback
     */
    public function validateCompleteIntegrity(): array
    {
        $issues = [];

        // Validar backend
        $backendIssues = $this->validateBackendIntegrity();
        $issues = array_merge($issues, $backendIssues);

        // Validar frontend
        $frontendIssues = $this->validateFrontendIntegrity();
        $issues = array_merge($issues, $frontendIssues);

        // Validar integridade entre frontend e backend
        $integrationIssues = $this->validateIntegrationIntegrity();
        $issues = array_merge($issues, $integrationIssues);

        return $issues;
    }

    /**
     * Valida integridade do backend
     */
    public function validateBackendIntegrity(): array
    {
        $issues = [];

        // Verificar migrations
        $migrationIssues = $this->validateMigrations();
        $issues = array_merge($issues, $migrationIssues);

        // Verificar models
        $modelIssues = $this->validateModels();
        $issues = array_merge($issues, $modelIssues);

        // Verificar controllers
        $controllerIssues = $this->validateControllers();
        $issues = array_merge($issues, $controllerIssues);

        // Verificar rotas
        $routeIssues = $this->validateRoutes();
        $issues = array_merge($issues, $routeIssues);

        // Verificar services
        $serviceIssues = $this->validateServices();
        $issues = array_merge($issues, $serviceIssues);

        return $issues;
    }

    /**
     * Valida integridade do frontend
     */
    public function validateFrontendIntegrity(): array
    {
        $issues = [];

        if (!is_dir($this->frontendPath)) {
            return ["Frontend não encontrado em: {$this->frontendPath}"];
        }

        // Verificar arquivos críticos
        $criticalFiles = [
            'package.json',
            'src/main.ts',
            'src/App.vue',
            'src/router/index.ts',
            'src/stores/index.ts'
        ];

        foreach ($criticalFiles as $file) {
            $fullPath = $this->frontendPath . '/' . $file;
            if (!file_exists($fullPath)) {
                $issues[] = "Arquivo crítico ausente: {$file}";
            }
        }

        // Verificar sintaxe de arquivos JSON
        $jsonFiles = ['package.json', 'tsconfig.json', 'vite.config.ts'];
        foreach ($jsonFiles as $file) {
            $fullPath = $this->frontendPath . '/' . $file;
            if (file_exists($fullPath) && str_ends_with($file, '.json')) {
                $jsonIssues = $this->validateJsonFile($fullPath);
                $issues = array_merge($issues, $jsonIssues);
            }
        }

        // Verificar estrutura de diretórios
        $structureIssues = $this->validateFrontendStructure();
        $issues = array_merge($issues, $structureIssues);

        return $issues;
    }

    /**
     * Valida integridade da integração entre frontend e backend
     */
    public function validateIntegrationIntegrity(): array
    {
        $issues = [];

        // Verificar se rotas API estão consistentes
        $apiIssues = $this->validateApiConsistency();
        $issues = array_merge($issues, $apiIssues);

        // Verificar se tipos TypeScript batem com models Laravel
        $typeIssues = $this->validateTypeConsistency();
        $issues = array_merge($issues, $typeIssues);

        return $issues;
    }

    /**
     * Valida migrations
     */
    private function validateMigrations(): array
    {
        $issues = [];

        try {
            // Verificar se há migrations pendentes
            $pendingMigrations = $this->getPendingMigrations();
            if (!empty($pendingMigrations)) {
                $issues[] = "Migrations pendentes encontradas: " . implode(', ', $pendingMigrations);
            }

            // Verificar se database está acessível
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $issues[] = "Problema de conexão com database: " . $e->getMessage();
        }

        return $issues;
    }

    /**
     * Valida models
     */
    private function validateModels(): array
    {
        $issues = [];
        $modelsPath = app_path('Domains');

        if (is_dir($modelsPath)) {
            $modelFiles = $this->scanForPhpFiles($modelsPath, 'Models');

            foreach ($modelFiles as $modelFile) {
                $modelIssues = $this->validateModelFile($modelFile);
                $issues = array_merge($issues, $modelIssues);
            }
        }

        return $issues;
    }

    /**
     * Valida controllers
     */
    private function validateControllers(): array
    {
        $issues = [];
        $controllersPath = app_path('Http/Controllers');

        if (is_dir($controllersPath)) {
            $controllerFiles = $this->scanForPhpFiles($controllersPath, 'Controller');

            foreach ($controllerFiles as $controllerFile) {
                $controllerIssues = $this->validateControllerFile($controllerFile);
                $issues = array_merge($issues, $controllerIssues);
            }
        }

        return $issues;
    }

    /**
     * Valida rotas
     */
    private function validateRoutes(): array
    {
        $issues = [];

        try {
            // Verificar se rotas podem ser carregadas
            $routes = app('router')->getRoutes();

            // Verificar rotas duplicadas
            $routeNames = [];
            foreach ($routes as $route) {
                $name = $route->getName();
                if ($name && isset($routeNames[$name])) {
                    $issues[] = "Rota duplicada encontrada: {$name}";
                } else {
                    $routeNames[$name] = true;
                }
            }
        } catch (\Exception $e) {
            $issues[] = "Erro ao carregar rotas: " . $e->getMessage();
        }

        return $issues;
    }

    /**
     * Valida services
     */
    private function validateServices(): array
    {
        $issues = [];
        $servicesPath = app_path('Domains');

        if (is_dir($servicesPath)) {
            $serviceFiles = $this->scanForPhpFiles($servicesPath, 'Services');

            foreach ($serviceFiles as $serviceFile) {
                $serviceIssues = $this->validateServiceFile($serviceFile);
                $issues = array_merge($issues, $serviceIssues);
            }
        }

        return $issues;
    }

    /**
     * Valida estrutura do frontend
     */
    private function validateFrontendStructure(): array
    {
        $issues = [];

        $requiredDirs = [
            'src',
            'src/components',
            'src/pages',
            'src/stores',
            'src/types',
            'src/router'
        ];

        foreach ($requiredDirs as $dir) {
            $fullPath = $this->frontendPath . '/' . $dir;
            if (!is_dir($fullPath)) {
                $issues[] = "Diretório ausente: {$dir}";
            }
        }

        return $issues;
    }

    /**
     * Valida consistência da API
     */
    private function validateApiConsistency(): array
    {
        $issues = [];

        // Verificar se todas as rotas API têm controllers correspondentes
        try {
            $apiRoutes = app('router')->getRoutes()->getRoutesByMethod()['GET'] ?? [];

            foreach ($apiRoutes as $route) {
                $uri = $route->uri();
                if (str_starts_with($uri, 'api/')) {
                    $action = $route->getAction('controller');
                    if ($action && !class_exists($action)) {
                        $issues[] = "Controller não encontrado para rota: {$uri}";
                    }
                }
            }
        } catch (\Exception $e) {
            $issues[] = "Erro ao validar consistência da API: " . $e->getMessage();
        }

        return $issues;
    }

    /**
     * Valida consistência de tipos
     */
    private function validateTypeConsistency(): array
    {
        $issues = [];

        // Verificar se arquivos de tipos TypeScript existem para models principais
        $modelsPath = app_path('Domains');
        $typesPath = $this->frontendPath . '/src/types';

        if (is_dir($modelsPath) && is_dir($typesPath)) {
            $modelFiles = $this->scanForPhpFiles($modelsPath, 'Models');

            foreach ($modelFiles as $modelFile) {
                $modelName = basename($modelFile, '.php');
                $typeFile = $typesPath . '/' . $modelName . '.ts';

                if (!file_exists($typeFile)) {
                    $issues[] = "Arquivo de tipo TypeScript ausente: {$modelName}.ts";
                }
            }
        }

        return $issues;
    }

    /**
     * Obtém migrations pendentes
     */
    private function getPendingMigrations(): array
    {
        try {
            $migrationFiles = glob(database_path('migrations/*.php'));
            $runMigrations = DB::table('migrations')->pluck('migration')->toArray();

            $pendingMigrations = [];
            foreach ($migrationFiles as $file) {
                $migrationName = pathinfo($file, PATHINFO_FILENAME);
                if (!in_array($migrationName, $runMigrations)) {
                    $pendingMigrations[] = $migrationName;
                }
            }

            return $pendingMigrations;
        } catch (\Exception $e) {
            return ["Erro ao verificar migrations: " . $e->getMessage()];
        }
    }

    /**
     * Escaneia por arquivos PHP
     */
    private function scanForPhpFiles(string $directory, string $suffix = ''): array
    {
        $files = [];

        if (!is_dir($directory)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $fileName = $file->getFilename();
                if (!$suffix || str_contains($fileName, $suffix)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * Valida um arquivo JSON
     */
    private function validateJsonFile(string $filePath): array
    {
        $issues = [];

        if (!file_exists($filePath)) {
            return $issues;
        }

        $content = file_get_contents($filePath);
        $decoded = json_decode($content);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $issues[] = "JSON inválido em: " . basename($filePath) . " - " . json_last_error_msg();
        }

        return $issues;
    }

    /**
     * Valida um arquivo de model
     */
    private function validateModelFile(string $filePath): array
    {
        $issues = [];

        if (!file_exists($filePath)) {
            return $issues;
        }

        $content = file_get_contents($filePath);

        // Verificar sintaxe básica
        if (strpos($content, '<?php') === false) {
            $issues[] = "Arquivo PHP inválido: " . basename($filePath);
        }

        // Verificar se extends Model
        if (!str_contains($content, 'extends Model') && !str_contains($content, 'extends BaseModel')) {
            $issues[] = "Model não estende classe base: " . basename($filePath);
        }

        return $issues;
    }

    /**
     * Valida um arquivo de controller
     */
    private function validateControllerFile(string $filePath): array
    {
        $issues = [];

        if (!file_exists($filePath)) {
            return $issues;
        }

        $content = file_get_contents($filePath);

        // Verificar sintaxe básica
        if (strpos($content, '<?php') === false) {
            $issues[] = "Arquivo PHP inválido: " . basename($filePath);
        }

        // Verificar se extends Controller
        if (!str_contains($content, 'extends Controller') && !str_contains($content, 'extends BaseController')) {
            $issues[] = "Controller não estende classe base: " . basename($filePath);
        }

        return $issues;
    }

    /**
     * Valida um arquivo de service
     */
    private function validateServiceFile(string $filePath): array
    {
        $issues = [];

        if (!file_exists($filePath)) {
            return $issues;
        }

        $content = file_get_contents($filePath);

        // Verificar sintaxe básica
        if (strpos($content, '<?php') === false) {
            $issues[] = "Arquivo PHP inválido: " . basename($filePath);
        }

        return $issues;
    }

    /**
     * Gera relatório de integridade
     */
    public function generateIntegrityReport(array $issues): string
    {
        $report = "\n" . str_repeat('=', 60) . "\n";
        $report .= "🔍 RELATÓRIO DE INTEGRIDADE DO PROJETO\n";
        $report .= str_repeat('=', 60) . "\n";

        if (empty($issues)) {
            $report .= "✅ PROJETO ÍNTEGRO\n";
            $report .= "Nenhum problema de integridade detectado.\n";
        } else {
            $report .= "⚠️  PROBLEMAS DETECTADOS: " . count($issues) . "\n";
            $report .= str_repeat('-', 60) . "\n";

            foreach ($issues as $issue) {
                $report .= "  ❌ $issue\n";
            }

            $report .= "\n💡 RECOMENDAÇÕES:\n";
            $report .= "  1. Revise os problemas listados acima\n";
            $report .= "  2. Execute os comandos de build para verificar compilação\n";
            $report .= "  3. Execute os testes para garantir funcionalidade\n";
            $report .= "  4. Considere re-executar a geração se necessário\n";
        }

        $report .= str_repeat('=', 60) . "\n";

        return $report;
    }
}
