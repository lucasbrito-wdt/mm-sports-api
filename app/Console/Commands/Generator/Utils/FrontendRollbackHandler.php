<?php

namespace App\Console\Commands\Generator\Utils;

use Illuminate\Console\Command;

class FrontendRollbackHandler
{
    private Command $command;
    private string $frontendPath;

    public function __construct(Command $command)
    {
        $this->command = $command;
        $this->setFrontendPath();
    }

    /**
     * Define o caminho do frontend baseado na estrutura do projeto
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

        // Fallback para diretório padrão
        $this->frontendPath = base_path('../frontend');
    }
    /**
     * Executa rollback específico para arquivos de frontend
     */
    public function rollbackFrontendFiles(array $files): array
    {
        $results = [
            'success' => [],
            'failed' => [],
            'stores_updated' => [],
            'routes_updated' => [],
            'types_updated' => [],
            'components_updated' => [],
            'pages_updated' => [],
            'services_updated' => []
        ];

        foreach ($files as $file) {
            $result = $this->rollbackSingleFile($file);

            if ($result['success']) {
                $results['success'][] = $file;

                // Acompanhar tipos especiais de rollback
                if (str_contains($file, '/stores/')) {
                    $results['stores_updated'][] = $file;
                } elseif (str_contains($file, '/types/')) {
                    $results['types_updated'][] = $file;
                } elseif (str_contains($file, 'router') || str_contains($file, 'routes')) {
                    $results['routes_updated'][] = $file;
                } elseif (str_contains($file, '/components/')) {
                    $results['components_updated'][] = $file;
                } elseif (str_contains($file, '/pages/') || str_contains($file, '/views/')) {
                    $results['pages_updated'][] = $file;
                } elseif (str_contains($file, '/services/')) {
                    $results['services_updated'][] = $file;
                }
            } else {
                $results['failed'][] = [
                    'file' => $file,
                    'error' => $result['error']
                ];
            }
        }

        // Executar rollbacks especiais após processamento dos arquivos
        $this->performSpecialRollbacks($results);

        return $results;
    }

    /**
     * Executa rollback específico para um domínio
     */
    public function rollbackDomain(string $domain, array $sessionData = []): array
    {
        $this->command->info("🎯 Executando rollback do domínio: $domain no frontend");

        // Obter arquivos relacionados ao domínio
        $domainFiles = $this->getDomainFiles($domain, $sessionData);

        if (empty($domainFiles)) {
            $this->command->warn("  ⚠️  Nenhum arquivo de frontend encontrado para o domínio: $domain");
            return [
                'success' => [],
                'failed' => [],
                'domain' => $domain,
                'message' => 'Nenhum arquivo encontrado'
            ];
        }

        $this->command->line("  📁 Encontrados " . count($domainFiles) . " arquivos de frontend para rollback");

        // Executar rollback dos arquivos
        $results = $this->rollbackFrontendFiles($domainFiles);
        $results['domain'] = $domain;

        // Executar limpeza específica do domínio
        $this->performDomainCleanup($domain, $results);

        return $results;
    }

    /**
     * Obtém arquivos relacionados a um domínio específico
     */
    private function getDomainFiles(string $domain, array $sessionData = []): array
    {
        $files = [];

        // Se temos dados da sessão, usar eles primeiro
        if (!empty($sessionData['files'])) {
            foreach ($sessionData['files'] as $file) {
                if ($this->isFileRelatedToDomain($file, $domain)) {
                    $files[] = $file;
                }
            }
        }

        // Buscar arquivos padrão do domínio se não temos dados da sessão
        if (empty($files)) {
            $files = $this->scanDomainFiles($domain);
        }

        return array_unique($files);
    }

    /**
     * Verifica se um arquivo está relacionado a um domínio
     */
    private function isFileRelatedToDomain(string $file, string $domain): bool
    {
        $domainLower = strtolower($domain);
        $fileLower = strtolower($file);

        // Verificar se o nome do domínio está no caminho do arquivo
        $patterns = [
            "/{$domainLower}/",
            "/{$domainLower}s/", // plural
            "/components.*{$domainLower}/",
            "/pages.*{$domainLower}/",
            "/views.*{$domainLower}/",
            "/stores.*{$domainLower}/",
            "/types.*{$domainLower}/",
            "/services.*{$domainLower}/",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $fileLower)) {
                return true;
            }
        }

        // Verificar também pelo conteúdo do nome do arquivo
        $fileName = basename($file, '.vue');
        $fileName = basename($fileName, '.ts');
        $fileName = basename($fileName, '.js');

        return str_contains(strtolower($fileName), $domainLower);
    }

    /**
     * Escaneia arquivos relacionados a um domínio
     */
    private function scanDomainFiles(string $domain): array
    {
        $files = [];

        if (!is_dir($this->frontendPath)) {
            return $files;
        }

        $searchPaths = [
            $this->frontendPath . '/src/components',
            $this->frontendPath . '/src/pages',
            $this->frontendPath . '/src/views',
            $this->frontendPath . '/src/stores',
            $this->frontendPath . '/src/types',
            $this->frontendPath . '/src/services',
            $this->frontendPath . '/src/composables'
        ];

        foreach ($searchPaths as $searchPath) {
            if (is_dir($searchPath)) {
                $files = array_merge($files, $this->scanDirectoryForDomain($searchPath, $domain));
            }
        }

        return $files;
    }

    /**
     * Escaneia um diretório procurando arquivos relacionados ao domínio
     */
    private function scanDirectoryForDomain(string $directory, string $domain): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filePath = $file->getPathname();
                if ($this->isFileRelatedToDomain($filePath, $domain) && $this->isFrontendFile($filePath)) {
                    $files[] = $filePath;
                }
            }
        }

        return $files;
    }

    /**
     * Executa rollback de um único arquivo
     */
    private function rollbackSingleFile(string $file): array
    {
        try {
            // Verificar se é um arquivo de frontend
            if (!$this->isFrontendFile($file)) {
                return ['success' => false, 'error' => 'Não é um arquivo de frontend'];
            }

            if (file_exists($file)) {
                // Tentar fazer backup antes de deletar
                $backupPath = $file . '.rollback_backup_' . time();
                if (copy($file, $backupPath)) {
                    if (unlink($file)) {
                        $this->command->line("  🗑️  Removido: " . $this->getRelativePath($file));
                        return ['success' => true];
                    } else {
                        // Restaurar backup se falhou ao deletar
                        rename($backupPath, $file);
                        return ['success' => false, 'error' => 'Falha ao deletar arquivo'];
                    }
                } else {
                    return ['success' => false, 'error' => 'Falha ao criar backup'];
                }
            } else {
                // Arquivo não existe, consideramos como sucesso
                return ['success' => true];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verifica se é um arquivo de frontend
     */
    private function isFrontendFile(string $file): bool
    {
        $frontendExtensions = ['.vue', '.ts', '.js', '.tsx', '.jsx'];
        $frontendPaths = ['/src/', '/components/', '/pages/', '/stores/', '/types/', '/composables/'];

        // Verificar extensão
        $hasValidExtension = false;
        foreach ($frontendExtensions as $ext) {
            if (str_ends_with($file, $ext)) {
                $hasValidExtension = true;
                break;
            }
        }

        if (!$hasValidExtension) {
            return false;
        }

        // Verificar caminho
        foreach ($frontendPaths as $path) {
            if (str_contains($file, $path)) {
                return true;
            }
        }

        // Verificar se está no diretório de frontend
        return str_starts_with($file, $this->frontendPath);
    }
    /**
     * Executa rollbacks especiais após processamento dos arquivos
     */
    private function performSpecialRollbacks(array &$results): void
    {
        // Rollback de stores Pinia
        if (!empty($results['stores_updated'])) {
            $this->rollbackPiniaStores($results['stores_updated']);
            $this->command->line("  🏪 Rollback de stores Pinia executado");
        }

        // Rollback de tipos TypeScript
        if (!empty($results['types_updated'])) {
            $this->rollbackTypeDefinitions($results['types_updated']);
            $this->command->line("  📝 Rollback de definições de tipos executado");
        }

        // Rollback de rotas
        if (!empty($results['routes_updated'])) {
            $this->rollbackRoutes($results['routes_updated']);
            $this->command->line("  🛣️  Rollback de rotas executado");
        }

        // Rollback de componentes
        if (!empty($results['components_updated'])) {
            $this->rollbackComponents($results['components_updated']);
            $this->command->line("  🧩 Rollback de componentes executado");
        }

        // Rollback de serviços
        if (!empty($results['services_updated'])) {
            $this->rollbackServices($results['services_updated']);
            $this->command->line("  🔧 Rollback de serviços executado");
        }
    }

    /**
     * Executa limpeza específica do domínio
     */
    private function performDomainCleanup(string $domain, array &$results): void
    {
        $this->command->line("  🧹 Executando limpeza específica do domínio: $domain");

        // Limpar imports e exports relacionados ao domínio
        $this->cleanupDomainImports($domain);

        // Limpar rotas relacionadas ao domínio
        $this->cleanupDomainRoutes($domain);

        // Limpar navegação relacionada ao domínio
        $this->cleanupDomainNavigation($domain);

        // Limpar stores relacionadas ao domínio
        $this->cleanupDomainStores($domain);

        $this->command->line("  ✅ Limpeza do domínio concluída");
    }

    /**
     * Limpa imports e exports relacionados ao domínio
     */
    private function cleanupDomainImports(string $domain): void
    {
        $indexFiles = [
            $this->frontendPath . '/src/components/index.ts',
            $this->frontendPath . '/src/pages/index.ts',
            $this->frontendPath . '/src/views/index.ts',
            $this->frontendPath . '/src/types/index.ts',
            $this->frontendPath . '/src/services/index.ts'
        ];

        foreach ($indexFiles as $indexFile) {
            if (file_exists($indexFile)) {
                $this->removeDomainReferencesFromFile($indexFile, $domain);
            }
        }
    }

    /**
     * Limpa rotas relacionadas ao domínio
     */
    private function cleanupDomainRoutes(string $domain): void
    {
        $routerFiles = [
            $this->frontendPath . '/src/router/index.ts',
            $this->frontendPath . '/src/router/routes.ts'
        ];

        foreach ($routerFiles as $routerFile) {
            if (file_exists($routerFile)) {
                $this->removeDomainRoutesFromFile($routerFile, $domain);
            }
        }
    }

    /**
     * Limpa navegação relacionada ao domínio
     */
    private function cleanupDomainNavigation(string $domain): void
    {
        $navFiles = [
            $this->frontendPath . '/src/components/Navigation.vue',
            $this->frontendPath . '/src/components/Sidebar.vue',
            $this->frontendPath . '/src/components/Menu.vue',
            $this->frontendPath . '/src/layouts/MainLayout.vue'
        ];

        foreach ($navFiles as $navFile) {
            if (file_exists($navFile)) {
                $this->removeDomainNavigationFromFile($navFile, $domain);
            }
        }
    }

    /**
     * Limpa stores relacionadas ao domínio
     */
    private function cleanupDomainStores(string $domain): void
    {
        $storeIndexPath = $this->frontendPath . '/src/stores/index.ts';

        if (file_exists($storeIndexPath)) {
            $this->removeDomainStoreReferences($storeIndexPath, $domain);
        }
    }

    /**
     * Remove referências do domínio de um arquivo
     */
    private function removeDomainReferencesFromFile(string $filePath, string $domain): void
    {
        $content = file_get_contents($filePath);
        $originalContent = $content;
        $domainLower = strtolower($domain);
        $domainUpper = ucfirst($domain);
        $domainCamel = lcfirst($domain);

        // Padrões para remover imports/exports relacionados ao domínio
        $patterns = [
            // Import statements
            "/import\s+[^;]*{$domainLower}[^;]*;?\s*\n?/i",
            "/import\s+[^;]*{$domainUpper}[^;]*;?\s*\n?/i",
            "/import\s+[^;]*{$domainCamel}[^;]*;?\s*\n?/i",
            // Export statements
            "/export\s+[^;]*{$domainLower}[^;]*;?\s*\n?/i",
            "/export\s+[^;]*{$domainUpper}[^;]*;?\s*\n?/i",
            "/export\s+[^;]*{$domainCamel}[^;]*;?\s*\n?/i",
            // Export * from statements
            "/export\s+\*\s+from\s+['\"][^'\"]*{$domainLower}[^'\"]*['\"];?\s*\n?/i",
            "/export\s+\*\s+from\s+['\"][^'\"]*{$domainUpper}[^'\"]*['\"];?\s*\n?/i",
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
        }
    }

    /**
     * Remove rotas do domínio de um arquivo de rotas
     */
    private function removeDomainRoutesFromFile(string $filePath, string $domain): void
    {
        $content = file_get_contents($filePath);
        $originalContent = $content;
        $domainLower = strtolower($domain);
        $domainUpper = ucfirst($domain);

        // Padrões para remover rotas relacionadas ao domínio
        $patterns = [
            // Definições de rota simples
            "/{\s*[^}]*path\s*:\s*['\"][^'\"]*{$domainLower}[^'\"]*['\"][^}]*}\s*,?\s*\n?/i",
            "/{\s*[^}]*name\s*:\s*['\"][^'\"]*{$domainLower}[^'\"]*['\"][^}]*}\s*,?\s*\n?/i",
            "/{\s*[^}]*component\s*:\s*{$domainUpper}[^}]*}\s*,?\s*\n?/i",
            // Imports de componentes para rotas
            "/import\s+{$domainUpper}[^;]*;?\s*\n?/i",
            "/import\s+[^;]*{$domainUpper}[^;]*from[^;]*;?\s*\n?/i",
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        // Remover vírgulas duplicadas que podem ter sido deixadas
        $content = preg_replace('/,\s*,/', ',', $content);
        $content = preg_replace('/,\s*]/', ']', $content);
        $content = preg_replace('/,\s*}/', '}', $content);

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
        }
    }

    /**
     * Remove navegação do domínio de arquivos de navegação
     */
    private function removeDomainNavigationFromFile(string $filePath, string $domain): void
    {
        $content = file_get_contents($filePath);
        $originalContent = $content;
        $domainLower = strtolower($domain);
        $domainUpper = ucfirst($domain);

        // Padrões para remover itens de navegação
        $patterns = [
            // Links de navegação em templates
            "/<router-link[^>]*to[^>]*{$domainLower}[^>]*>[^<]*<\/router-link>\s*\n?/i",
            "/<a[^>]*href[^>]*{$domainLower}[^>]*>[^<]*<\/a>\s*\n?/i",
            // Itens de menu em arrays JavaScript
            "/{\s*[^}]*text\s*:\s*['\"][^'\"]*{$domainUpper}[^'\"]*['\"][^}]*}\s*,?\s*\n?/i",
            "/{\s*[^}]*label\s*:\s*['\"][^'\"]*{$domainUpper}[^'\"]*['\"][^}]*}\s*,?\s*\n?/i",
            "/{\s*[^}]*to\s*:\s*['\"][^'\"]*{$domainLower}[^'\"]*['\"][^}]*}\s*,?\s*\n?/i",
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
        }
    }

    /**
     * Remove referências de stores do domínio
     */
    private function removeDomainStoreReferences(string $filePath, string $domain): void
    {
        $content = file_get_contents($filePath);
        $originalContent = $content;
        $domainLower = strtolower($domain);
        $domainUpper = ucfirst($domain);
        $storeName = "use{$domainUpper}Store";

        // Padrões para remover stores
        $patterns = [
            "/import\s+{\s*{$storeName}\s*}\s+from[^;]*;?\s*\n?/i",
            "/export\s+{\s*{$storeName}\s*}[^;]*;?\s*\n?/i",
            "/export\s+\*\s+from\s+['\"][^'\"]*{$domainLower}[^'\"]*['\"];?\s*\n?/i",
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
        }
    }

    /**
     * Rollback específico para stores Pinia
     */
    private function rollbackPiniaStores(array $storeFiles): void
    {
        $indexStorePath = $this->frontendPath . '/src/stores/index.ts';

        if (!file_exists($indexStorePath)) {
            return;
        }

        $content = file_get_contents($indexStorePath);
        $modified = false;

        foreach ($storeFiles as $storeFile) {
            $storeName = $this->extractStoreNameFromPath($storeFile);
            if ($storeName) {
                // Remover import
                $importPattern = "/import\s+{\s*use{$storeName}Store\s*}\s+from\s+['\"][^'\"]*['\"];?\s*\n?/i";
                if (preg_match($importPattern, $content)) {
                    $content = preg_replace($importPattern, '', $content);
                    $modified = true;
                }

                // Remover export
                $exportPattern = "/export\s+{\s*use{$storeName}Store\s*}[^;]*;?\s*\n?/i";
                if (preg_match($exportPattern, $content)) {
                    $content = preg_replace($exportPattern, '', $content);
                    $modified = true;
                }
            }
        }

        if ($modified) {
            file_put_contents($indexStorePath, $content);
        }
    }

    /**
     * Rollback específico para definições de tipos
     */
    private function rollbackTypeDefinitions(array $typeFiles): void
    {
        $mainTypesPath = $this->frontendPath . '/src/types/index.ts';

        if (!file_exists($mainTypesPath)) {
            return;
        }

        $content = file_get_contents($mainTypesPath);
        $modified = false;

        foreach ($typeFiles as $typeFile) {
            $typeName = $this->extractTypeNameFromPath($typeFile);
            if ($typeName) {
                // Remover imports e exports relacionados
                $patterns = [
                    "/export\s+\*\s+from\s+['\"][^'\"]*{$typeName}[^'\"]*['\"];?\s*\n?/i",
                    "/import[^;]*{$typeName}[^;]*;?\s*\n?/i",
                    "/export\s+{\s*[^}]*{$typeName}[^}]*\s*}[^;]*;?\s*\n?/i"
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $content = preg_replace($pattern, '', $content);
                        $modified = true;
                    }
                }
            }
        }

        if ($modified) {
            file_put_contents($mainTypesPath, $content);
        }
    }
    /**
     * Rollback específico para rotas
     */
    private function rollbackRoutes(array $routeFiles): void
    {
        $routerPath = $this->frontendPath . '/src/router/index.ts';

        if (!file_exists($routerPath)) {
            return;
        }

        $content = file_get_contents($routerPath);
        $modified = false;

        foreach ($routeFiles as $routeFile) {
            $routeName = $this->extractRouteNameFromPath($routeFile);
            if ($routeName) {
                // Remover importações de componentes
                $importPattern = "/import\s+{$routeName}[^;]*;?\s*\n?/i";
                if (preg_match($importPattern, $content)) {
                    $content = preg_replace($importPattern, '', $content);
                    $modified = true;
                }

                // Remover definições de rotas
                $routePattern = "/{\s*[^}]*name\s*:\s*['\"][^'\"]*{$routeName}[^'\"]*['\"][^}]*}\s*,?\s*\n?/i";
                if (preg_match($routePattern, $content)) {
                    $content = preg_replace($routePattern, '', $content);
                    $modified = true;
                }
            }
        }

        if ($modified) {
            file_put_contents($routerPath, $content);
        }
    }

    /**
     * Rollback específico para componentes
     */
    private function rollbackComponents(array $componentFiles): void
    {
        $componentIndexPath = $this->frontendPath . '/src/components/index.ts';

        if (!file_exists($componentIndexPath)) {
            return;
        }

        $content = file_get_contents($componentIndexPath);
        $modified = false;

        foreach ($componentFiles as $componentFile) {
            $componentName = $this->extractComponentNameFromPath($componentFile);
            if ($componentName) {
                // Remover imports e exports relacionados
                $patterns = [
                    "/export\s+{\s*default\s+as\s+{$componentName}\s*}\s+from\s+['\"][^'\"]*['\"];?\s*\n?/i",
                    "/import\s+{$componentName}[^;]*;?\s*\n?/i",
                    "/export\s+{\s*{$componentName}\s*}[^;]*;?\s*\n?/i"
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $content = preg_replace($pattern, '', $content);
                        $modified = true;
                    }
                }
            }
        }

        if ($modified) {
            file_put_contents($componentIndexPath, $content);
        }
    }

    /**
     * Rollback específico para serviços
     */
    private function rollbackServices(array $serviceFiles): void
    {
        $serviceIndexPath = $this->frontendPath . '/src/services/index.ts';

        if (!file_exists($serviceIndexPath)) {
            return;
        }

        $content = file_get_contents($serviceIndexPath);
        $modified = false;

        foreach ($serviceFiles as $serviceFile) {
            $serviceName = $this->extractServiceNameFromPath($serviceFile);
            if ($serviceName) {
                // Remover imports e exports relacionados
                $patterns = [
                    "/export\s+\*\s+from\s+['\"][^'\"]*{$serviceName}[^'\"]*['\"];?\s*\n?/i",
                    "/import\s+[^;]*{$serviceName}[^;]*;?\s*\n?/i",
                    "/export\s+{\s*[^}]*{$serviceName}[^}]*\s*}[^;]*;?\s*\n?/i"
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $content = preg_replace($pattern, '', $content);
                        $modified = true;
                    }
                }
            }
        }

        if ($modified) {
            file_put_contents($serviceIndexPath, $content);
        }
    }
    /**
     * Extrai nome da store do caminho do arquivo
     */
    private function extractStoreNameFromPath(string $path): ?string
    {
        if (preg_match('/\/stores\/([^\/]+)\.ts$/', $path, $matches)) {
            return ucfirst($matches[1]);
        }
        return null;
    }

    /**
     * Extrai nome do tipo do caminho do arquivo
     */
    private function extractTypeNameFromPath(string $path): ?string
    {
        if (preg_match('/\/types\/([^\/]+)\.ts$/', $path, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extrai nome da rota do caminho do arquivo
     */
    private function extractRouteNameFromPath(string $path): ?string
    {
        if (preg_match('/\/(?:pages|views)\/([^\/]+)\.vue$/', $path, $matches)) {
            return ucfirst($matches[1]);
        }
        return null;
    }

    /**
     * Extrai nome do componente do caminho do arquivo
     */
    private function extractComponentNameFromPath(string $path): ?string
    {
        if (preg_match('/\/components\/([^\/]+)\.vue$/', $path, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extrai nome do serviço do caminho do arquivo
     */
    private function extractServiceNameFromPath(string $path): ?string
    {
        if (preg_match('/\/services\/([^\/]+)\.ts$/', $path, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Retorna caminho relativo para melhor visualização
     */
    private function getRelativePath(string $fullPath): string
    {
        $basePath = base_path();
        if (str_starts_with($fullPath, $basePath)) {
            return str_replace($basePath . DIRECTORY_SEPARATOR, '', $fullPath);
        }
        return $fullPath;
    }

    /**
     * Verifica integridade dos arquivos de frontend
     */
    public function verifyFrontendIntegrity(): array
    {
        $issues = [];

        // Verificar arquivos principais
        $criticalFiles = [
            $this->frontendPath . '/package.json',
            $this->frontendPath . '/src/main.ts',
            $this->frontendPath . '/src/App.vue',
            $this->frontendPath . '/src/router/index.ts',
            $this->frontendPath . '/src/stores/index.ts'
        ];

        foreach ($criticalFiles as $file) {
            if (!file_exists($file)) {
                $issues[] = "Arquivo crítico não encontrado: " . $this->getRelativePath($file);
            }
        }

        // Verificar sintaxe de arquivos TypeScript/Vue críticos
        if (empty($issues)) {
            $syntaxIssues = $this->checkSyntaxIssues();
            $issues = array_merge($issues, $syntaxIssues);
        }

        return $issues;
    }

    /**
     * Verifica problemas de sintaxe em arquivos críticos
     */
    private function checkSyntaxIssues(): array
    {
        $issues = [];

        // Verificar arquivos de configuração JSON
        $jsonFiles = [
            $this->frontendPath . '/package.json',
            $this->frontendPath . '/tsconfig.json'
        ];

        foreach ($jsonFiles as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if (json_decode($content) === null && json_last_error() !== JSON_ERROR_NONE) {
                    $issues[] = "Sintaxe JSON inválida em: " . $this->getRelativePath($file);
                }
            }
        }

        return $issues;
    }
    /**
     * Gera relatório de rollback de frontend
     */
    public function generateFrontendRollbackReport(array $results): string
    {
        $report = "\n" . str_repeat('=', 60) . "\n";
        $report .= "🔄 RELATÓRIO DE ROLLBACK - FRONTEND\n";
        $report .= str_repeat('=', 60) . "\n";

        // Estatísticas gerais
        $report .= "✅ Arquivos removidos com sucesso: " . count($results['success']) . "\n";
        $report .= "❌ Falhas ao remover arquivos: " . count($results['failed']) . "\n";

        // Estatísticas específicas
        $report .= "🏪 Stores atualizadas: " . count($results['stores_updated']) . "\n";
        $report .= "📝 Arquivos de tipos processados: " . count($results['types_updated']) . "\n";
        $report .= "🛣️  Rotas processadas: " . count($results['routes_updated']) . "\n";
        $report .= "🧩 Componentes processados: " . count($results['components_updated']) . "\n";
        $report .= "📄 Páginas processadas: " . count($results['pages_updated']) . "\n";
        $report .= "🔧 Serviços processados: " . count($results['services_updated']) . "\n";

        // Informação do domínio se disponível
        if (isset($results['domain'])) {
            $report .= "🎯 Domínio processado: " . $results['domain'] . "\n";
        }

        // Detalhes dos arquivos processados
        if (!empty($results['success'])) {
            $report .= "\n✅ ARQUIVOS REMOVIDOS COM SUCESSO:\n";
            foreach ($results['success'] as $file) {
                $report .= "  - " . $this->getRelativePath($file) . "\n";
            }
        }

        // Falhas detalhadas
        if (!empty($results['failed'])) {
            $report .= "\n❌ FALHAS:\n";
            foreach ($results['failed'] as $failure) {
                $report .= "  - {$failure['file']}: {$failure['error']}\n";
            }
        }

        // Verificar integridade após rollback
        $integrityIssues = $this->verifyFrontendIntegrity();
        if (!empty($integrityIssues)) {
            $report .= "\n⚠️  PROBLEMAS DE INTEGRIDADE DETECTADOS:\n";
            foreach ($integrityIssues as $issue) {
                $report .= "  - $issue\n";
            }
            $report .= "\n💡 RECOMENDAÇÃO: Execute 'npm install' e 'npm run build' para verificar se há problemas de compilação.\n";
        } else {
            $report .= "\n✅ Integridade do frontend verificada com sucesso!\n";
        }

        $report .= str_repeat('=', 60) . "\n";

        return $report;
    }

    /**
     * Gera relatório específico para rollback de domínio
     */
    public function generateDomainRollbackReport(string $domain, array $results): string
    {
        $report = "\n" . str_repeat('=', 70) . "\n";
        $report .= "🎯 RELATÓRIO DE ROLLBACK DE DOMÍNIO - FRONTEND\n";
        $report .= str_repeat('=', 70) . "\n";
        $report .= "📦 Domínio: $domain\n";
        $report .= str_repeat('-', 70) . "\n";

        // Estatísticas específicas do domínio
        $totalFiles = count($results['success']) + count($results['failed']);
        $report .= "📊 ESTATÍSTICAS:\n";
        $report .= "  📁 Total de arquivos encontrados: $totalFiles\n";
        $report .= "  ✅ Arquivos removidos com sucesso: " . count($results['success']) . "\n";
        $report .= "  ❌ Falhas ao remover: " . count($results['failed']) . "\n";

        // Breakdown por tipo de arquivo
        $typeBreakdown = $this->getFileTypeBreakdown($results);
        if (!empty($typeBreakdown)) {
            $report .= "\n📋 BREAKDOWN POR TIPO:\n";
            foreach ($typeBreakdown as $type => $count) {
                $icon = $this->getFileTypeIcon($type);
                $report .= "  $icon $type: $count arquivos\n";
            }
        }

        // Limpeza realizada
        $report .= "\n🧹 LIMPEZA REALIZADA:\n";
        $report .= "  🗂️  Imports/exports removidos dos arquivos índice\n";
        $report .= "  🛣️  Rotas relacionadas ao domínio removidas\n";
        $report .= "  🧭 Navegação relacionada ao domínio limpa\n";
        $report .= "  🏪 Stores relacionadas ao domínio limpas\n";

        // Arquivos processados por categoria
        $this->addCategoryDetails($report, $results);

        // Problemas encontrados
        if (!empty($results['failed'])) {
            $report .= "\n❌ PROBLEMAS ENCONTRADOS:\n";
            foreach ($results['failed'] as $failure) {
                $report .= "  - " . basename($failure['file']) . ": {$failure['error']}\n";
            }
        }

        // Verificação final
        $integrityIssues = $this->verifyFrontendIntegrity();
        if (!empty($integrityIssues)) {
            $report .= "\n⚠️  ATENÇÃO - PROBLEMAS DE INTEGRIDADE:\n";
            foreach ($integrityIssues as $issue) {
                $report .= "  - $issue\n";
            }
        } else {
            $report .= "\n✅ INTEGRIDADE VERIFICADA: Frontend está consistente!\n";
        }

        $report .= "\n💡 PRÓXIMOS PASSOS RECOMENDADOS:\n";
        $report .= "  1. Execute 'npm run type-check' para verificar tipos TypeScript\n";
        $report .= "  2. Execute 'npm run build' para validar a compilação\n";
        $report .= "  3. Teste a aplicação para garantir que tudo funciona corretamente\n";

        $report .= str_repeat('=', 70) . "\n";

        return $report;
    }

    /**
     * Obtém breakdown de arquivos por tipo
     */
    private function getFileTypeBreakdown(array $results): array
    {
        $breakdown = [];

        foreach ($results['success'] as $file) {
            $type = $this->identifyFileType($file);
            $breakdown[$type] = ($breakdown[$type] ?? 0) + 1;
        }

        return $breakdown;
    }

    /**
     * Identifica o tipo de arquivo
     */
    private function identifyFileType(string $file): string
    {
        if (str_contains($file, '/components/')) return 'Componentes';
        if (str_contains($file, '/pages/')) return 'Páginas';
        if (str_contains($file, '/views/')) return 'Views';
        if (str_contains($file, '/stores/')) return 'Stores';
        if (str_contains($file, '/types/')) return 'Tipos';
        if (str_contains($file, '/services/')) return 'Serviços';
        if (str_contains($file, '/composables/')) return 'Composables';
        if (str_ends_with($file, '.vue')) return 'Componentes Vue';
        if (str_ends_with($file, '.ts')) return 'TypeScript';
        if (str_ends_with($file, '.js')) return 'JavaScript';

        return 'Outros';
    }

    /**
     * Obtém ícone para tipo de arquivo
     */
    private function getFileTypeIcon(string $type): string
    {
        $icons = [
            'Componentes' => '🧩',
            'Páginas' => '📄',
            'Views' => '👁️',
            'Stores' => '🏪',
            'Tipos' => '📝',
            'Serviços' => '🔧',
            'Composables' => '🔨',
            'Componentes Vue' => '💚',
            'TypeScript' => '📘',
            'JavaScript' => '📜',
        ];

        return $icons[$type] ?? '📄';
    }

    /**
     * Adiciona detalhes por categoria ao relatório
     */
    private function addCategoryDetails(string &$report, array $results): void
    {
        $categories = [
            'stores_updated' => ['🏪 STORES', 'Stores Pinia processadas'],
            'components_updated' => ['🧩 COMPONENTES', 'Componentes Vue processados'],
            'pages_updated' => ['📄 PÁGINAS', 'Páginas/Views processadas'],
            'types_updated' => ['📝 TIPOS', 'Definições TypeScript processadas'],
            'services_updated' => ['🔧 SERVIÇOS', 'Serviços API processados'],
            'routes_updated' => ['🛣️ ROTAS', 'Arquivos de rota processados']
        ];

        foreach ($categories as $key => $info) {
            if (!empty($results[$key])) {
                $report .= "\n{$info[0]} ({$info[1]}):\n";
                foreach ($results[$key] as $file) {
                    $report .= "  - " . basename($file) . "\n";
                }
            }
        }
    }
}
