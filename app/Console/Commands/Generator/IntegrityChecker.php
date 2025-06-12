<?php

namespace App\Console\Commands\Generator;

use App\Console\Commands\Generator\Utils\IntegrityValidator;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class IntegrityChecker extends Command
{
    protected $signature = 'rollback:integrity {--frontend-only} {--backend-only} {--detailed} {--fix}';
    protected $description = 'Verifica a integridade do projeto após operações de rollback';

    private IntegrityValidator $validator;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new IntegrityValidator($this);
    }

    public function handle(): int
    {
        $this->info('🔍 Verificador de Integridade do Projeto');

        $scope = $this->determineScope();
        $issues = $this->performValidation($scope);

        if (empty($issues)) {
            $this->info("✅ Projeto íntegro! Nenhum problema detectado.");
            return CommandAlias::SUCCESS;
        }

        $this->displayIssues($issues);

        if ($this->option('fix') || confirm('Deseja tentar corrigir automaticamente alguns problemas?', false)) {
            $this->attemptAutoFix($issues);
        }

        return empty($issues) ? CommandAlias::SUCCESS : CommandAlias::FAILURE;
    }

    /**
     * Determina o escopo da verificação
     */
    private function determineScope(): string
    {
        if ($this->option('frontend-only')) {
            return 'frontend';
        }

        if ($this->option('backend-only')) {
            return 'backend';
        }

        if ($this->hasOption('detailed') && !$this->option('detailed')) {
            $scope = select(
                label: 'Escolha o escopo da verificação:',
                options: [
                    'complete' => '🔄 Verificação Completa',
                    'frontend' => '🎨 Apenas Frontend',
                    'backend' => '🔧 Apenas Backend',
                    'integration' => '🔗 Apenas Integração'
                ]
            );
            return $scope;
        }

        return 'complete';
    }

    /**
     * Executa validação baseada no escopo
     */
    private function performValidation(string $scope): array
    {
        $this->info("📊 Executando verificação: " . $this->getScopeLabel($scope));

        switch ($scope) {
            case 'frontend':
                return $this->validator->validateFrontendIntegrity();
            case 'backend':
                return $this->validator->validateBackendIntegrity();
            case 'integration':
                return $this->validator->validateIntegrationIntegrity();
            case 'complete':
            default:
                return $this->validator->validateCompleteIntegrity();
        }
    }

    /**
     * Obtém label do escopo
     */
    private function getScopeLabel(string $scope): string
    {
        $labels = [
            'frontend' => 'Frontend',
            'backend' => 'Backend',
            'integration' => 'Integração',
            'complete' => 'Completa'
        ];

        return $labels[$scope] ?? 'Completa';
    }

    /**
     * Exibe problemas encontrados
     */
    private function displayIssues(array $issues): void
    {
        $this->error("⚠️  Problemas de integridade detectados: " . count($issues));
        $this->newLine();

        // Categorizar problemas
        $categories = $this->categorizeIssues($issues);

        foreach ($categories as $category => $categoryIssues) {
            if (!empty($categoryIssues)) {
                $this->warn("📂 {$category}:");
                foreach ($categoryIssues as $issue) {
                    $this->line("  ❌ $issue");
                }
                $this->newLine();
            }
        }

        // Mostrar relatório detalhado se solicitado
        if ($this->option('detailed')) {
            $report = $this->validator->generateIntegrityReport($issues);
            $this->line($report);
        }
    }

    /**
     * Categoriza problemas por tipo
     */
    private function categorizeIssues(array $issues): array
    {
        $categories = [
            'Frontend' => [],
            'Backend' => [],
            'Database' => [],
            'Integração' => [],
            'Outros' => []
        ];

        foreach ($issues as $issue) {
            $category = $this->determineIssueCategory($issue);
            $categories[$category][] = $issue;
        }

        return array_filter($categories);
    }

    /**
     * Determina categoria do problema
     */
    private function determineIssueCategory(string $issue): string
    {
        $issue = strtolower($issue);

        if (str_contains($issue, 'frontend') || str_contains($issue, 'vue') || str_contains($issue, 'typescript')) {
            return 'Frontend';
        }

        if (str_contains($issue, 'controller') || str_contains($issue, 'model') || str_contains($issue, 'service')) {
            return 'Backend';
        }

        if (str_contains($issue, 'migration') || str_contains($issue, 'database') || str_contains($issue, 'conexão')) {
            return 'Database';
        }

        if (str_contains($issue, 'api') || str_contains($issue, 'rota') || str_contains($issue, 'tipo')) {
            return 'Integração';
        }

        return 'Outros';
    }

    /**
     * Tenta corrigir automaticamente alguns problemas
     */
    private function attemptAutoFix(array &$issues): void
    {
        $this->info("🔧 Tentando correções automáticas...");

        $fixedIssues = [];

        foreach ($issues as $index => $issue) {
            $fixed = $this->tryFixIssue($issue);
            if ($fixed) {
                $fixedIssues[] = $issue;
                unset($issues[$index]);
                $this->info("  ✅ Corrigido: " . substr($issue, 0, 60) . "...");
            }
        }

        $issues = array_values($issues); // Re-index array

        if (!empty($fixedIssues)) {
            $this->info("🎉 Correções aplicadas: " . count($fixedIssues));
        } else {
            $this->warn("⚠️  Nenhuma correção automática disponível para os problemas encontrados.");
        }

        // Sugerir ações manuais
        if (!empty($issues)) {
            $this->suggestManualActions($issues);
        }
    }

    /**
     * Tenta corrigir um problema específico
     */
    private function tryFixIssue(string $issue): bool
    {
        $issue = strtolower($issue);

        // Correção de diretórios ausentes
        if (str_contains($issue, 'diretório ausente:')) {
            return $this->createMissingDirectory($issue);
        }

        // Correção de arquivos críticos ausentes
        if (str_contains($issue, 'arquivo crítico ausente:')) {
            return $this->createMissingCriticalFile($issue);
        }

        // Outras correções podem ser adicionadas aqui

        return false;
    }

    /**
     * Cria diretório ausente
     */
    private function createMissingDirectory(string $issue): bool
    {
        // Extrair nome do diretório da mensagem de erro
        if (preg_match('/diretório ausente:\s*(.+)/', $issue, $matches)) {
            $directory = trim($matches[1]);
            $fullPath = $this->getFrontendPath() . '/' . $directory;

            if (!is_dir($fullPath)) {
                return mkdir($fullPath, 0755, true);
            }
        }

        return false;
    }

    /**
     * Cria arquivo crítico ausente
     */
    private function createMissingCriticalFile(string $issue): bool
    {
        // Esta é uma operação mais complexa que pode precisar de templates
        // Por agora, apenas reportamos que não foi possível corrigir
        return false;
    }

    /**
     * Sugere ações manuais
     */
    private function suggestManualActions(array $issues): void
    {
        $this->warn("💡 Ações manuais recomendadas:");

        $suggestions = [
            "1. Execute 'npm install' no frontend para restaurar dependências",
            "2. Execute 'php artisan migrate' para aplicar migrations pendentes",
            "3. Execute 'composer dump-autoload' para recarregar classes",
            "4. Verifique manualmente os arquivos relatados como problemáticos",
            "5. Execute os testes para garantir funcionalidade: 'php artisan test'",
            "6. Execute build do frontend: 'npm run build'",
        ];

        foreach ($suggestions as $suggestion) {
            $this->line("  $suggestion");
        }
    }

    /**
     * Obtém caminho do frontend
     */
    private function getFrontendPath(): string
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
                return $path;
            }
        }

        return base_path('../frontend');
    }
}
