<?php

namespace App\Console\Commands\Generator;

use App\Console\Commands\Generator\Generators\FrontEnd\FrontendPathTrait;
use App\Console\Commands\Generator\Utils\FrontendRollbackHandler;
use App\Console\Commands\Generator\Utils\IntegrityValidator;
use App\Console\Commands\Generator\Utils\RouteManager;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class RollbackManager extends Command
{
    use FrontendPathTrait;

    protected $signature = 'rollback:manager {--domain=} {--interactive} {--frontend-only} {--backend-only} {--dry-run} {--force}';

    protected $description = 'Gerenciador avançado de rollback com seleção de domínios e frontend/backend';

    private RouteManager $routeManager;

    private FrontendRollbackHandler $frontendHandler;

    // private IntegrityValidator $integrityValidator;
    private string $rollbackLogPath;

    private Collection $rollbackLog;

    private array $rollbackDomain = [];

    public function __construct(RouteManager $routeManager)
    {
        parent::__construct();
        $this->routeManager = $routeManager;
        $this->frontendHandler = new FrontendRollbackHandler($this);
        // $this->integrityValidator = new IntegrityValidator($this);
        $this->rollbackLogPath = storage_path('framework/rollback/rollback_log.json');
    }

    public function handle(): int
    {
        $this->info('🔄 Gerenciador Avançado de Rollback');

        if (! file_exists($this->rollbackLogPath)) {
            $this->error('❌ Nenhum log de rollback encontrado. Nada a desfazer.');

            return CommandAlias::FAILURE;
        }

        // Carregar log de rollback
        $this->rollbackLog = collect(json_decode(file_get_contents($this->rollbackLogPath), true)['sessions']);
        if (! $this->rollbackLog || ! is_array($this->rollbackLog->toArray())) {
            $this->error('❌ Log de rollback corrompido ou inválido.');

            return CommandAlias::FAILURE;
        }

        $this->rollbackDomain = $this->rollbackLog->where('domain', $this->option('domain'))->values()->first();

        // Mostrar resumo do que será desfeito
        $this->showRollbackSummary();

        // Modo interativo ou direto
        if ($this->option('interactive')) {
            return $this->handleInteractiveRollback();
        }

        if ($this->option('domain')) {
            return $this->handleDomainSpecificRollback();
        }

        // Rollback completo
        return $this->handleFullRollback();
    }

    private function showRollbackSummary(): void
    {
        $this->info('📋 Resumo do que será desfeito:');

        $createdFiles = count($this->rollbackDomain['created'] ?? []);
        $modifiedFiles = count($this->rollbackDomain['modified'] ?? []);
        $directories = count($this->rollbackDomain['directories'] ?? []);

        $this->line("  📁 Arquivos criados: {$createdFiles}");
        $this->line("  📝 Arquivos modificados: {$modifiedFiles}");
        $this->line("  📂 Diretórios criados: {$directories}");

        // Análise por domínios
        $domains = $this->extractDomainsFromLog();
        if (! empty($domains)) {
            $this->line("\n🏗️ Domínios afetados:");
            foreach ($domains as $domain) {
                $this->line("  • {$domain}");
            }
        }

        // Análise frontend/backend
        $frontendFiles = $this->getFrontendFilesCount();
        $backendFiles = $createdFiles + $modifiedFiles - $frontendFiles;

        $this->line("\n🎨 Frontend: {$frontendFiles} arquivos");
        $this->line("🔧 Backend: {$backendFiles} arquivos");
    }

    private function handleInteractiveRollback(): int
    {
        $this->info('🎯 Modo Interativo');

        $options = [
            'full' => '🔄 Rollback Completo',
            'selective' => '🎯 Rollback Seletivo por Domínio',
            'frontend' => '🎨 Apenas Frontend',
            'backend' => '🔧 Apenas Backend',
            'files' => '📁 Seleção Manual de Arquivos',
        ];

        $choice = select(
            label: 'Escolha o tipo de rollback:',
            options: $options
        );

        switch ($choice) {
            case 'full':
                return $this->handleFullRollback();
            case 'selective':
                return $this->handleDomainSpecificRollback();
            case 'frontend':
                return $this->handleFrontendOnlyRollback();
            case 'backend':
                return $this->handleBackendOnlyRollback();
            case 'files':
                return $this->handleFileSpecificRollback();
            default:
                return CommandAlias::FAILURE;
        }
    }

    private function handleDomainSpecificRollback(): int
    {
        $domains = $this->extractDomainsFromLog();

        if (empty($domains)) {
            $this->error('❌ Nenhum domínio identificado no log.');

            return CommandAlias::FAILURE;
        }

        $selectedDomain = $this->option('domain');

        if (! $selectedDomain) {
            $selectedDomain = select(
                label: 'Selecione o domínio para rollback:',
                options: array_combine($domains, $domains)
            );
        }

        if (! in_array($selectedDomain, $domains)) {
            $this->error("❌ Domínio '{$selectedDomain}' não encontrado no log.");

            return CommandAlias::FAILURE;
        }

        $this->info("🎯 Executando rollback para o domínio: {$selectedDomain}");

        if (! $this->option('force') && ! confirm("Confirma o rollback do domínio '{$selectedDomain}'?", false)) {
            $this->info('❌ Operação cancelada.');

            return CommandAlias::SUCCESS;
        }

        return $this->executeDomainRollback($selectedDomain);
    }

    private function handleFullRollback(): int
    {
        if ($this->option('dry-run')) {
            $this->info('🔍 Simulação de rollback completo (dry-run):');
            $this->simulateRollback();

            return CommandAlias::SUCCESS;
        }

        if (! $this->option('force') && ! confirm('⚠️ Confirma o rollback COMPLETO? Esta ação não pode ser desfeita.', false)) {
            $this->info('❌ Operação cancelada.');

            return CommandAlias::SUCCESS;
        }

        $this->info('🔄 Executando rollback completo...');

        // Rollback migrations se aplicável
        if (confirm('Deseja fazer rollback das migrations também?', true)) {
            $this->rollbackMigrations();
        }
        $this->executeFullRollback();

        // Verificar integridade após rollback
        $this->performIntegrityCheck();

        $this->info('✅ Rollback completo executado com sucesso!');

        return CommandAlias::SUCCESS;
    }

    private function handleFrontendOnlyRollback(): int
    {
        $this->info('🎨 Executando rollback apenas do frontend...');

        // Oferecer opção de rollback por domínio específico
        $domains = $this->extractDomainsFromLog();

        if (! empty($domains) && count($domains) > 1) {
            $choice = select(
                label: 'Escolha o escopo do rollback de frontend:',
                options: [
                    'all' => '🔄 Todo o frontend',
                    'domain' => '🎯 Domínio específico',
                ]
            );

            if ($choice === 'domain') {
                $selectedDomain = select(
                    label: 'Selecione o domínio para rollback no frontend:',
                    options: array_combine($domains, $domains)
                );

                if (! $this->option('force') && ! confirm("Confirma o rollback do domínio '{$selectedDomain}' apenas no frontend?", true)) {
                    $this->info('❌ Operação cancelada.');

                    return CommandAlias::SUCCESS;
                }
                $this->executeDomainFrontendRollback($selectedDomain);

                // Verificar integridade do frontend
                $this->performFrontendIntegrityCheck();

                $this->info("✅ Rollback do domínio '{$selectedDomain}' no frontend executado com sucesso!");

                return CommandAlias::SUCCESS;
            }
        }

        if (! $this->option('force') && ! confirm('Confirma o rollback de todo o frontend?', true)) {
            $this->info('❌ Operação cancelada.');

            return CommandAlias::SUCCESS;
        }
        $this->executeFrontendRollback();

        // Verificar integridade do frontend
        $this->performFrontendIntegrityCheck();

        $this->info('✅ Rollback do frontend executado com sucesso!');

        return CommandAlias::SUCCESS;
    }

    private function handleBackendOnlyRollback(): int
    {
        $this->info('🔧 Executando rollback apenas do backend...');

        if (! $this->option('force') && ! confirm('Confirma o rollback apenas do backend?', true)) {
            $this->info('❌ Operação cancelada.');

            return CommandAlias::SUCCESS;
        }
        $this->executeBackendRollback();

        // Verificar integridade do backend
        $this->performBackendIntegrityCheck();

        $this->info('✅ Rollback do backend executado com sucesso!');

        return CommandAlias::SUCCESS;
    }

    private function handleFileSpecificRollback(): int
    {
        $allFiles = array_merge(
            array_keys($this->rollbackLog['modified'] ?? []),
            $this->rollbackLog['created'] ?? []
        );

        if (empty($allFiles)) {
            $this->error('❌ Nenhum arquivo encontrado para rollback.');

            return CommandAlias::FAILURE;
        }

        // Limitar a 20 arquivos para não sobrecarregar a interface
        $filesToShow = array_slice($allFiles, 0, 20);
        if (count($allFiles) > 20) {
            $this->warn("⚠️ Mostrando apenas os primeiros 20 de {count($allFiles)} arquivos.");
        }

        $selectedFiles = multiselect(
            label: 'Selecione os arquivos para rollback:',
            options: array_combine($filesToShow, $filesToShow)
        );

        if (empty($selectedFiles)) {
            $this->info('❌ Nenhum arquivo selecionado.');

            return CommandAlias::SUCCESS;
        }

        $this->executeFileSpecificRollback($selectedFiles);

        $this->info('✅ Rollback dos arquivos selecionados executado com sucesso!');

        return CommandAlias::SUCCESS;
    }

    private function executeDomainRollback(string $domain): int
    {
        $domainFiles = $this->getDomainFiles($domain);

        if (empty($domainFiles['created']) && empty($domainFiles['modified'])) {
            $this->warn("⚠️ Nenhum arquivo encontrado para o domínio '{$domain}'.");

            return CommandAlias::SUCCESS;
        }

        $this->info("🎯 Processando rollback do domínio: $domain");

        // Separar arquivos de frontend e backend
        $frontendFiles = [];
        $backendFiles = [];
        $frontendPath = $this->getFrontendPath();

        // Classificar arquivos modificados
        foreach ($domainFiles['modified'] as $file => $backup) {
            if (str_starts_with($file, $frontendPath)) {
                $frontendFiles[] = $file;
            } else {
                $backendFiles[] = ['file' => $file, 'backup' => $backup];
            }
        }

        // Classificar arquivos criados
        foreach ($domainFiles['created'] as $file) {
            if (str_starts_with($file, $frontendPath)) {
                $frontendFiles[] = $file;
            } else {
                $backendFiles[] = ['file' => $file, 'backup' => null];
            }
        }

        // Executar rollback de frontend usando o FrontendRollbackHandler
        if (! empty($frontendFiles) && ! $this->option('backend-only')) {
            $this->executeDomainFrontendRollback($domain);
        }

        // Executar rollback de backend
        if (! empty($backendFiles) && ! $this->option('frontend-only')) {
            $this->info('🔧 Processando arquivos de backend do domínio...');

            foreach ($backendFiles as $fileData) {
                $file = $fileData['file'];
                $backup = $fileData['backup'];

                if ($backup && file_exists($backup)) {
                    // Restaurar arquivo modificado
                    copy($backup, $file);
                    $this->info('  ✓ Arquivo restaurado: '.basename($file));
                } elseif (! $backup && file_exists($file)) {
                    // Remover arquivo criado
                    unlink($file);
                    $this->info('  ✓ Arquivo removido: '.basename($file));
                }
            }
        }

        // Remover rotas do domínio
        if ($this->routeManager->removeDomainRoutes($domain)) {
            $this->info("  🛣️  Rotas do domínio $domain removidas");
        }        // Remover diretórios vazios do domínio
        $this->removeEmptyDomainDirectories($domain);

        // Verificar integridade após rollback do domínio
        $this->performIntegrityCheck();

        $this->info("✅ Rollback do domínio '$domain' concluído com sucesso!");

        return CommandAlias::SUCCESS;
    }

    private function executeFullRollback(): void
    {
        $frontendPath = $this->getFrontendPath();

        // Remover rotas de domínios criados
        foreach ($this->rollbackLog['created'] ?? [] as $file) {
            if (str_contains($file, 'routes/domains/') && str_ends_with($file, '.php')) {
                $domainName = basename($file, '.php');
                $domainName = Str::studly(str_replace('-', '', $domainName));

                if ($this->routeManager->removeDomainRoutes($domainName)) {
                    $this->info("  ✓ Rotas do domínio {$domainName} removidas");
                }
            }
        }

        // Coletar arquivos de frontend para processamento específico
        $frontendFiles = [];
        foreach ($this->rollbackLog['modified'] ?? [] as $file => $backup) {
            if (str_starts_with($file, $frontendPath)) {
                $frontendFiles[] = $file;
            }
        }
        foreach ($this->rollbackLog['created'] ?? [] as $file) {
            if (str_starts_with($file, $frontendPath)) {
                $frontendFiles[] = $file;
            }
        }

        // Processar arquivos de frontend com handler especializado
        if (! empty($frontendFiles)) {
            $this->info('🎨 Processando arquivos de frontend...');
            $frontendResults = $this->frontendHandler->rollbackFrontendFiles($frontendFiles);
            $this->line($this->frontendHandler->generateFrontendRollbackReport($frontendResults));
        }

        // Restaurar arquivos modificados (exceto frontend já processado)
        foreach ($this->rollbackLog['modified'] ?? [] as $file => $backup) {
            if (! str_starts_with($file, $frontendPath) && file_exists($backup)) {
                copy($backup, $file);
                $this->info("  ✓ Arquivo restaurado: {$file}");
            }
        }

        // Remover arquivos criados (exceto frontend já processado)
        foreach ($this->rollbackLog['created'] ?? [] as $file) {
            if (! str_starts_with($file, $frontendPath) && file_exists($file)) {
                unlink($file);
                $this->info("  ✓ Arquivo removido: {$file}");
            }
        }

        // Remover diretórios criados (em ordem reversa)
        foreach (array_reverse($this->rollbackLog['directories'] ?? []) as $dir) {
            if (is_dir($dir) && count(scandir($dir)) === 2) {
                rmdir($dir);
                $this->info("  ✓ Diretório removido: {$dir}");
            }
        }

        // Limpar log
        unlink($this->rollbackLogPath);

        // Limpar backup directory
        $backupDir = storage_path('framework/rollback/backups');
        if (is_dir($backupDir)) {
            File::deleteDirectory($backupDir);
        }
    }

    private function executeFrontendRollback(): void
    {
        $frontendPath = $this->getFrontendPath();

        // Coletar arquivos de frontend para o handler
        $frontendFiles = [];

        // Arquivos modificados do frontend
        foreach ($this->rollbackLog['modified'] ?? [] as $file => $backup) {
            if (str_starts_with($file, $frontendPath)) {
                $frontendFiles[] = $file;
            }
        }

        // Arquivos criados do frontend
        foreach ($this->rollbackLog['created'] ?? [] as $file) {
            if (str_starts_with($file, $frontendPath)) {
                $frontendFiles[] = $file;
            }
        }

        if (! empty($frontendFiles)) {
            $this->info('🎨 Processando '.count($frontendFiles).' arquivos de frontend...');

            // Usar o FrontendRollbackHandler para processamento específico
            $results = $this->frontendHandler->rollbackFrontendFiles($frontendFiles);

            // Mostrar relatório detalhado
            $report = $this->frontendHandler->generateFrontendRollbackReport($results);
            $this->line($report);
        } else {
            $this->warn('⚠️ Nenhum arquivo de frontend encontrado para rollback.');
        }

        // Remover diretórios vazios do frontend
        foreach (array_reverse($this->rollbackLog['directories'] ?? []) as $dir) {
            if (str_starts_with($dir, $frontendPath) && is_dir($dir) && count(scandir($dir)) === 2) {
                rmdir($dir);
                $this->info('  ✓ Diretório frontend removido: '.basename($dir));
            }
        }
    }

    /**
     * Executa rollback específico de um domínio no frontend
     */
    private function executeDomainFrontendRollback(string $domain): void
    {
        $this->info("🎯 Executando rollback do domínio '$domain' no frontend...");

        // Coletar dados da sessão para o domínio
        $sessionData = $this->extractDomainSessionData($domain);

        // Usar o FrontendRollbackHandler para rollback específico do domínio
        $results = $this->frontendHandler->rollbackDomain($domain, $sessionData);

        if (empty($results['success']) && empty($results['failed'])) {
            $this->warn("  ⚠️  Nenhum arquivo de frontend encontrado para o domínio '$domain'");

            return;
        }

        // Mostrar relatório específico do domínio
        $report = $this->frontendHandler->generateDomainRollbackReport($domain, $results);
        $this->line($report);

        // Remover diretórios específicos do domínio se estiverem vazios
        $this->cleanupDomainDirectories($domain);
    }

    /**
     * Extrai dados da sessão específicos de um domínio
     */
    private function extractDomainSessionData(string $domain): array
    {
        $sessionData = ['files' => []];
        $domainLower = strtolower($domain);

        // Coletar arquivos relacionados ao domínio dos logs
        foreach ($this->rollbackLog['created'] ?? [] as $file) {
            if (str_contains(strtolower($file), $domainLower)) {
                $sessionData['files'][] = $file;
            }
        }

        foreach ($this->rollbackLog['modified'] ?? [] as $file => $backup) {
            if (str_contains(strtolower($file), $domainLower)) {
                $sessionData['files'][] = $file;
            }
        }

        return $sessionData;
    }

    /**
     * Limpa diretórios específicos do domínio
     */
    private function cleanupDomainDirectories(string $domain): void
    {
        $frontendPath = $this->getFrontendPath();
        $domainLower = strtolower($domain);

        $possibleDirs = [
            $frontendPath.'/src/components/'.$domain,
            $frontendPath.'/src/components/'.$domainLower,
            $frontendPath.'/src/pages/'.$domain,
            $frontendPath.'/src/pages/'.$domainLower,
            $frontendPath.'/src/views/'.$domain,
            $frontendPath.'/src/views/'.$domainLower,
        ];

        foreach ($possibleDirs as $dir) {
            if (is_dir($dir) && $this->isDirectoryEmpty($dir)) {
                rmdir($dir);
                $this->info('  🗂️  Diretório do domínio removido: '.basename($dir));
            }
        }
    }

    /**
     * Verifica se um diretório está vazio
     */
    private function isDirectoryEmpty(string $dir): bool
    {
        $items = scandir($dir);

        return count($items) === 2; // apenas . e ..
    }

    /**
     * Executa verificação de integridade do projeto
     */
    private function performIntegrityCheck(): void
    {
        $this->info('🔍 Verificando integridade do projeto...');

        // Temporariamente desabilitado - será reativado quando IntegrityValidator for corrigido
        // $issues = $this->integrityValidator->validateCompleteIntegrity();

        $this->info('✅ Verificação de integridade temporariamente desabilitada');
    }

    /**
     * Executa verificação de integridade apenas do frontend
     */
    private function performFrontendIntegrityCheck(): void
    {
        $this->info('🔍 Verificando integridade do frontend...');

        // Usar o método do FrontendRollbackHandler
        $issues = $this->frontendHandler->verifyFrontendIntegrity();

        if (empty($issues)) {
            $this->info('✅ Integridade do frontend verificada com sucesso!');
        } else {
            $this->warn('⚠️  Problemas de integridade no frontend:');
            foreach ($issues as $issue) {
                $this->line("  - $issue");
            }
        }
    }

    /**
     * Executa verificação de integridade apenas do backend
     */
    private function performBackendIntegrityCheck(): void
    {
        $this->info('🔍 Verificando integridade do backend...');

        // Verificação básica do backend
        $issues = [];

        // Verificar se controllers básicos existem
        $controllersPath = app_path('Http/Controllers');
        if (! is_dir($controllersPath)) {
            $issues[] = 'Diretório de controllers não encontrado';
        }

        // Verificar se models básicos existem
        $domainsPath = app_path('Domains');
        if (! is_dir($domainsPath)) {
            $issues[] = 'Diretório de domínios não encontrado';
        }

        if (empty($issues)) {
            $this->info('✅ Integridade do backend verificada com sucesso!');
        } else {
            $this->warn('⚠️  Problemas de integridade no backend:');
            foreach ($issues as $issue) {
                $this->line("  - $issue");
            }
        }
    }

    private function executeBackendRollback(): void
    {
        $frontendPath = $this->getFrontendPath();

        // Restaurar arquivos modificados do backend
        foreach ($this->rollbackLog['modified'] ?? [] as $file => $backup) {
            if (! str_starts_with($file, $frontendPath) && file_exists($backup)) {
                copy($backup, $file);
                $this->info('  ✓ Arquivo backend restaurado: '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $file));
            }
        }

        // Remover arquivos criados do backend
        foreach ($this->rollbackLog['created'] ?? [] as $file) {
            if (! str_starts_with($file, $frontendPath) && file_exists($file)) {
                unlink($file);
                $this->info('  ✓ Arquivo backend removido: '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $file));
            }
        }

        // Remover diretórios vazios do backend
        foreach (array_reverse($this->rollbackLog['directories'] ?? []) as $dir) {
            if (! str_starts_with($dir, $frontendPath) && is_dir($dir) && count(scandir($dir)) === 2) {
                rmdir($dir);
                $this->info('  ✓ Diretório backend removido: '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $dir));
            }
        }
    }

    private function executeFileSpecificRollback(array $selectedFiles): void
    {
        foreach ($selectedFiles as $file) {
            // Verificar se é arquivo modificado
            if (isset($this->rollbackLog['modified'][$file])) {
                $backup = $this->rollbackLog['modified'][$file];
                if (file_exists($backup)) {
                    copy($backup, $file);
                    $this->info('  ✓ Arquivo restaurado: '.basename($file));
                }
            }

            // Verificar se é arquivo criado
            if (in_array($file, $this->rollbackLog['created'] ?? [])) {
                if (file_exists($file)) {
                    unlink($file);
                    $this->info('  ✓ Arquivo removido: '.basename($file));
                }
            }
        }
    }

    private function rollbackMigrations(): void
    {
        $this->info('🗄️ Fazendo rollback das migrations...');

        try {
            // Identificar migrations criadas no log
            $migrations = [];
            foreach ($this->rollbackLog['created'] ?? [] as $file) {
                if (str_contains($file, 'Migration') && str_ends_with($file, '.php')) {
                    // Extrair nome da tabela do arquivo de migration
                    $fileName = basename($file);
                    if (preg_match('/create_(.+)_table\.php$/', $fileName, $matches)) {
                        $tableName = $matches[1];
                        $migrations[] = $tableName;
                    }
                }
            }

            if (! empty($migrations)) {
                foreach ($migrations as $table) {
                    if (DB::getSchemaBuilder()->hasTable($table)) {
                        Artisan::call('migrate:rollback', ['--step' => 1]);
                        $this->info("  ✓ Migration rollback para tabela: {$table}");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn('⚠️ Erro no rollback de migrations: '.$e->getMessage());
        }
    }

    private function simulateRollback(): void
    {
        $this->line('📁 Arquivos que seriam restaurados:');
        foreach ($this->rollbackLog['modified'] ?? [] as $file => $backup) {
            $this->line('  • '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $file));
        }

        $this->line('\n🗑️ Arquivos que seriam removidos:');
        foreach ($this->rollbackLog['created'] ?? [] as $file) {
            $this->line('  • '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $file));
        }

        $this->line('\n📂 Diretórios que seriam removidos:');
        foreach (array_reverse($this->rollbackLog['directories'] ?? []) as $dir) {
            $this->line('  • '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $dir));
        }
    }

    private function extractDomainsFromLog(): array
    {
        $domains = [];

        foreach ($this->rollbackLog['created'] ?? [] as $file) {
            if (preg_match('/app[\/\\\\]Domains[\/\\\\]([^\/\\\\]+)/', $file, $matches)) {
                $domains[] = $matches[1];
            }
        }

        foreach ($this->rollbackLog['modified'] ?? [] as $file => $backup) {
            if (preg_match('/app[\/\\\\]Domains[\/\\\\]([^\/\\\\]+)/', $file, $matches)) {
                $domains[] = $matches[1];
            }
        }

        return array_unique($domains);
    }

    private function getDomainFiles(string $domain): array
    {
        $domainFiles = ['created' => [], 'modified' => []];

        foreach ($this->rollbackLog['created'] ?? [] as $file) {
            if (str_contains($file, "Domains{DIRECTORY_SEPARATOR}{$domain}")) {
                $domainFiles['created'][] = $file;
            }
        }

        foreach ($this->rollbackLog['modified'] ?? [] as $file => $backup) {
            if (str_contains($file, "Domains{DIRECTORY_SEPARATOR}{$domain}")) {
                $domainFiles['modified'][$file] = $backup;
            }
        }

        return $domainFiles;
    }

    private function getFrontendFilesCount(): int
    {
        $count = 0;
        $frontendPath = $this->getFrontendPath();

        foreach ($this->rollbackLog['created'] ?? [] as $file) {
            if (str_starts_with($file, $frontendPath)) {
                $count++;
            }
        }

        foreach ($this->rollbackLog['modified'] ?? [] as $file => $backup) {
            if (str_starts_with($file, $frontendPath)) {
                $count++;
            }
        }

        return $count;
    }

    private function removeEmptyDomainDirectories(string $domain): void
    {
        $domainPath = app_path("Domains/{$domain}");

        if (is_dir($domainPath)) {
            $subdirs = ['Controllers', 'Models', 'Services', 'Requests', 'Migrations', 'Seeders'];

            foreach ($subdirs as $subdir) {
                $fullPath = $domainPath.DIRECTORY_SEPARATOR.$subdir;
                if (is_dir($fullPath) && count(scandir($fullPath)) === 2) {
                    rmdir($fullPath);
                    $this->info("  ✓ Diretório removido: {$subdir}");
                }
            }

            // Tentar remover o diretório do domínio se estiver vazio
            if (count(scandir($domainPath)) === 2) {
                rmdir($domainPath);
                $this->info("  ✓ Diretório do domínio removido: {$domain}");
            }
        }
    }
}
