<?php

namespace App\Console\Commands\Generator;

use App\Console\Commands\Generator\Utils\RollbackLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;

class RollbackStatus extends Command
{
    protected $signature = 'rollback:status {--detailed} {--session=} {--domain=}';
    protected $description = 'Mostra o status atual do sistema de rollback';

    private RollbackLogger $logger;

    public function __construct(RollbackLogger $logger)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    public function handle(): int
    {
        $this->info('📊 Status do Sistema de Rollback');
        $this->line('');

        // Verificar se há dados
        $sessions = $this->logger->getSessions();
        if (empty($sessions)) {
            $this->warn('⚠️ Nenhuma sessão de rollback encontrada.');
            $this->info('💡 Execute um comando de geração primeiro para criar dados de rollback.');
            return CommandAlias::SUCCESS;
        }

        // Estatísticas gerais
        $this->showGeneralStatistics();

        // Mostrar sessão específica se solicitada
        if ($sessionId = $this->option('session')) {
            return $this->showSessionDetails($sessionId);
        }

        // Mostrar sessões de domínio específico se solicitado
        if ($domain = $this->option('domain')) {
            return $this->showDomainSessions($domain);
        }

        // Mostrar detalhes se solicitado
        if ($this->option('detailed')) {
            return $this->showDetailedStatus();
        }

        // Status resumido
        return $this->showSummaryStatus();
    }

    private function showGeneralStatistics(): void
    {
        $stats = $this->logger->getStatistics();

        $this->info('📈 Estatísticas Gerais:');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total de Sessões', $stats['total_sessions']],
                ['Sessões Ativas', $stats['active_sessions']],
                ['Sessões Concluídas', $stats['completed_sessions']],
                ['Sessões com Falha', $stats['failed_sessions']],
                ['Arquivos Criados', $stats['total_files_created']],
                ['Arquivos Modificados', $stats['total_files_modified']],
                ['Diretórios Criados', $stats['total_directories_created']],
                ['Domínios Únicos', count($stats['domains'])],
            ]
        );

        if (!empty($stats['domains'])) {
            $this->line('');
            $this->info('🏗️ Domínios Afetados: ' . implode(', ', $stats['domains']));
        }

        if ($stats['oldest_session']) {
            $oldestDate = Carbon::parse($stats['oldest_session'])->format('d/m/Y H:i:s');
            $newestDate = Carbon::parse($stats['newest_session'])->format('d/m/Y H:i:s');
            $this->line('');
            $this->info("📅 Período: {$oldestDate} até {$newestDate}");
        }

        $this->line('');
    }

    private function showSessionDetails(string $sessionId): int
    {
        $session = $this->logger->getSession($sessionId);

        if (!$session) {
            $this->error("❌ Sessão '{$sessionId}' não encontrada.");
            return CommandAlias::FAILURE;
        }

        $this->info("🔍 Detalhes da Sessão: {$sessionId}");
        $this->line('');

        // Informações básicas
        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID', $session['id']],
                ['Domínio', $session['domain']],
                ['Ação', $session['action']],
                ['Status', $this->formatStatus($session['status'])],
                ['Usuário', $session['user'] ?? 'N/A'],
                ['Criado em', Carbon::parse($session['timestamp'])->format('d/m/Y H:i:s')],
                ['Concluído em', isset($session['completed_at']) ? Carbon::parse($session['completed_at'])->format('d/m/Y H:i:s') : 'N/A'],
            ]
        );

        // Metadata
        if (!empty($session['metadata'])) {
            $this->line('');
            $this->info('🔧 Metadados:');
            foreach ($session['metadata'] as $key => $value) {
                $this->line("  • {$key}: " . (is_bool($value) ? ($value ? 'Sim' : 'Não') : $value));
            }
        }        // Arquivos criados
        if (!empty($session['created'])) {
            $this->line('');
            $createdCount = count($session['created']);
            $this->info("📁 Arquivos Criados ({$createdCount}):");
            foreach (array_slice($session['created'], 0, 10) as $file) {
                $this->line("  ✓ " . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file));
            }
            if (count($session['created']) > 10) {
                $this->line("  ... e mais " . (count($session['created']) - 10) . " arquivos");
            }
        }

        // Arquivos modificados
        if (!empty($session['modified'])) {
            $this->line('');
            $this->info("📝 Arquivos Modificados (" . count($session['modified']) . "):");
            foreach (array_slice(array_keys($session['modified']), 0, 10) as $file) {
                $this->line("  ~ " . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file));
            }
            if (count($session['modified']) > 10) {
                $this->line("  ... e mais " . (count($session['modified']) - 10) . " arquivos");
            }
        }

        // Diretórios criados
        if (!empty($session['directories'])) {
            $this->line('');
            $this->info("📂 Diretórios Criados (" . count($session['directories']) . "):");
            foreach ($session['directories'] as $dir) {
                $this->line("  📁 " . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $dir));
            }
        }

        return CommandAlias::SUCCESS;
    }

    private function showDomainSessions(string $domain): int
    {
        $sessions = $this->logger->getSessionsByDomain($domain);

        if (empty($sessions)) {
            $this->warn("⚠️ Nenhuma sessão encontrada para o domínio '{$domain}'.");
            return CommandAlias::SUCCESS;
        }

        $this->info("🏗️ Sessões do Domínio: {$domain}");
        $this->line('');

        $tableData = [];
        foreach ($sessions as $session) {
            $tableData[] = [
                substr($session['id'], 0, 8) . '...',
                $session['action'],
                $this->formatStatus($session['status']),
                Carbon::parse($session['timestamp'])->format('d/m H:i'),
                count($session['created'] ?? []),
                count($session['modified'] ?? []),
            ];
        }

        $this->table(
            ['ID', 'Ação', 'Status', 'Data', 'Criados', 'Modificados'],
            $tableData
        );

        return CommandAlias::SUCCESS;
    }

    private function showDetailedStatus(): int
    {
        $sessions = $this->logger->getSessions();

        $this->info('📋 Status Detalhado das Sessões:');
        $this->line('');

        $tableData = [];
        foreach ($sessions as $session) {
            $tableData[] = [
                substr($session['id'], 0, 8) . '...',
                $session['domain'],
                $session['action'],
                $this->formatStatus($session['status']),
                $session['user'] ?? 'N/A',
                Carbon::parse($session['timestamp'])->format('d/m H:i'),
                count($session['created'] ?? []),
                count($session['modified'] ?? []),
                count($session['directories'] ?? []),
            ];
        }

        $this->table(
            ['ID', 'Domínio', 'Ação', 'Status', 'Usuário', 'Data', 'Criados', 'Modificados', 'Diretórios'],
            $tableData
        );

        // Opção para ver detalhes de uma sessão específica
        if (confirm('Deseja ver detalhes de alguma sessão específica?', false)) {
            $sessionIds = array_map(fn($s) => $s['id'], $sessions);
            $sessionOptions = array_combine($sessionIds, array_map(
                fn($s) =>
                substr($s['id'], 0, 8) . '... - ' . $s['domain'] . ' (' . $s['action'] . ')',
                $sessions
            ));

            $selectedSession = select(
                label: 'Selecione uma sessão:',
                options: $sessionOptions
            );

            return $this->showSessionDetails($selectedSession);
        }

        return CommandAlias::SUCCESS;
    }

    private function showSummaryStatus(): int
    {
        $sessions = $this->logger->getSessions();

        $this->info('📋 Resumo das Sessões Recentes:');
        $this->line('');

        // Mostrar últimas 10 sessões
        $recentSessions = array_slice(array_reverse($sessions), 0, 10);

        $tableData = [];
        foreach ($recentSessions as $session) {
            $totalFiles = count($session['created'] ?? []) + count($session['modified'] ?? []);
            $tableData[] = [
                substr($session['id'], 0, 8) . '...',
                $session['domain'],
                $session['action'],
                $this->formatStatus($session['status']),
                Carbon::parse($session['timestamp'])->format('d/m H:i'),
                $totalFiles,
            ];
        }

        $this->table(
            ['ID', 'Domínio', 'Ação', 'Status', 'Data', 'Total Arquivos'],
            $tableData
        );

        $this->line('');
        $this->info('💡 Comandos Úteis:');
        $this->line('  • rollback:status --detailed    - Ver todos os detalhes');
        $this->line('  • rollback:status --session=ID  - Ver sessão específica');
        $this->line('  • rollback:status --domain=NOME - Ver sessões de um domínio');
        $this->line('  • rollback:manager --interactive - Gerenciar rollbacks');

        return CommandAlias::SUCCESS;
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'active' => '🔄 Ativo',
            'completed' => '✅ Concluído',
            'failed' => '❌ Falhou',
            default => "❓ {$status}"
        };
    }
}
