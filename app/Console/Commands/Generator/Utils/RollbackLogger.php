<?php

namespace App\Console\Commands\Generator\Utils;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class RollbackLogger
{
    private string $rollbackLogPath;

    private array $rollbackLog = [];

    public function __construct()
    {
        $this->rollbackLogPath = storage_path('framework/rollback/rollback_log.json');
        $this->initializeLog();
    }

    /**
     * Inicializa o log de rollback
     */
    private function initializeLog(): void
    {
        if (! file_exists($this->rollbackLogPath)) {
            $this->createNewLog();
        } else {
            $this->loadExistingLog();
        }
    }

    /**
     * Cria um novo log de rollback
     */
    private function createNewLog(): void
    {
        $this->rollbackLog = [
            'version' => '2.0',
            'created_at' => Carbon::now()->toISOString(),
            'user' => $this->getCurrentUser(),
            'sessions' => [],
        ];
        $this->saveLog();
    }

    /**
     * Carrega o log existente
     */
    private function loadExistingLog(): void
    {
        $content = file_get_contents($this->rollbackLogPath);
        $this->rollbackLog = json_decode($content, true) ?: [];

        // Migrar log antigo se necessário
        if (! isset($this->rollbackLog['version'])) {
            $this->migrateOldLog();
        }
    }

    /**
     * Migra log antigo para novo formato
     */
    private function migrateOldLog(): void
    {
        $oldLog = $this->rollbackLog;
        $this->rollbackLog = [
            'version' => '2.0',
            'created_at' => Carbon::now()->toISOString(),
            'user' => 'migrated',
            'sessions' => [
                [
                    'id' => uniqid(),
                    'timestamp' => Carbon::now()->toISOString(),
                    'action' => 'migrated_session',
                    'domain' => 'unknown',
                    'created' => $oldLog['created'] ?? [],
                    'modified' => $oldLog['modified'] ?? [],
                    'directories' => $oldLog['directories'] ?? [],
                    'metadata' => [
                        'migrated' => true,
                    ],
                ],
            ],
        ];
        $this->saveLog();
    }

    /**
     * Inicia uma nova sessão de geração
     */
    public function startSession(string $action, string $domain, array $metadata = []): string
    {
        $sessionId = uniqid();

        $session = [
            'id' => $sessionId,
            'timestamp' => Carbon::now()->toISOString(),
            'action' => $action,
            'domain' => $domain,
            'user' => $this->getCurrentUser(),
            'created' => [],
            'modified' => [],
            'directories' => [],
            'metadata' => array_merge([
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ], $metadata),
            'status' => 'active',
        ];

        $this->rollbackLog['sessions'][] = $session;
        $this->saveLog();

        return $sessionId;
    }

    /**
     * Finaliza uma sessão
     */
    public function endSession(string $sessionId, string $status = 'completed'): void
    {
        foreach ($this->rollbackLog['sessions'] as &$session) {
            if ($session['id'] === $sessionId) {
                $session['status'] = $status;
                $session['completed_at'] = Carbon::now()->toISOString();
                break;
            }
        }
        $this->saveLog();
    }

    /**
     * Registra um arquivo criado
     */
    public function logCreatedFile(string $file, ?string $sessionId): void
    {
        if ($sessionId) {
            $this->addToSession($sessionId, 'created', $file);
        } else {
            $this->addToActiveSession('created', $file);
        }
    }

    /**
     * Registra um arquivo modificado
     */
    public function logModifiedFile(string $file, ?string $sessionId): void
    {
        $backupDir = storage_path('framework/rollback/backups');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $backupFile = $backupDir.'/'.md5($file).'_'.basename($file);

        if (File::exists($file)) {
            File::copy($file, $backupFile);

            if ($sessionId) {
                $this->addToSession($sessionId, 'modified', [
                    'file' => $file,
                    'backup' => $backupFile,
                ]);
            } else {
                $this->addToActiveSession('modified', [
                    'file' => $file,
                    'backup' => $backupFile,
                ]);
            }
        }
    }

    /**
     * Registra um diretório criado
     */
    public function logCreatedDirectory(string $directory, ?string $sessionId): void
    {
        if ($sessionId) {
            $this->addToSession($sessionId, 'directories', $directory);
        } else {
            $this->addToActiveSession('directories', $directory);
        }
    }

    /**
     * Adiciona item a uma sessão específica
     */
    private function addToSession(string $sessionId, string $type, $item): void
    {
        foreach ($this->rollbackLog['sessions'] as &$session) {
            if ($session['id'] === $sessionId) {
                $session[$type][] = $item;
                break;
            }
        }
        $this->saveLog();
    }

    /**
     * Adiciona item à última sessão ativa
     */
    private function addToActiveSession(string $type, $item): void
    {
        $activeSession = $this->getActiveSession();
        if ($activeSession) {
            $this->addToSession($activeSession['id'], $type, $item);
        }
    }

    /**
     * Obtém a sessão ativa atual
     */
    private function getActiveSession(): ?array
    {
        foreach (array_reverse($this->rollbackLog['sessions']) as $session) {
            if ($session['status'] === 'active') {
                return $session;
            }
        }

        return null;
    }

    /**
     * Obtém todas as sessões
     */
    public function getSessions(): array
    {
        return $this->rollbackLog['sessions'] ?? [];
    }

    /**
     * Obtém uma sessão específica
     */
    public function getSession(string $sessionId): ?array
    {
        foreach ($this->rollbackLog['sessions'] as $session) {
            if ($session['id'] === $sessionId) {
                return $session;
            }
        }

        return null;
    }

    /**
     * Obtém sessões por domínio
     */
    public function getSessionsByDomain(string $domain): array
    {
        return array_filter($this->rollbackLog['sessions'], function ($session) use ($domain) {
            return $session['domain'] === $domain;
        });
    }

    /**
     * Remove uma sessão específica
     */
    public function removeSession(string $sessionId): bool
    {
        $originalCount = count($this->rollbackLog['sessions']);

        $this->rollbackLog['sessions'] = array_filter(
            $this->rollbackLog['sessions'],
            function ($session) use ($sessionId) {
                return $session['id'] !== $sessionId;
            }
        );

        if (count($this->rollbackLog['sessions']) < $originalCount) {
            $this->saveLog();

            return true;
        }

        return false;
    }

    /**
     * Remove todas as sessões de um domínio
     */
    public function removeSessionsByDomain(string $domain): int
    {
        $originalCount = count($this->rollbackLog['sessions']);

        $this->rollbackLog['sessions'] = array_filter(
            $this->rollbackLog['sessions'],
            function ($session) use ($domain) {
                return $session['domain'] !== $domain;
            }
        );

        $removedCount = $originalCount - count($this->rollbackLog['sessions']);

        if ($removedCount > 0) {
            $this->saveLog();
        }

        return $removedCount;
    }

    /**
     * Limpa todo o log
     */
    public function clearLog(): void
    {
        // Remover backups
        $backupDir = storage_path('framework/rollback/backups');
        if (is_dir($backupDir)) {
            File::deleteDirectory($backupDir);
        }

        // Remover log
        if (file_exists($this->rollbackLogPath)) {
            unlink($this->rollbackLogPath);
        }

        $this->createNewLog();
    }

    /**
     * Obtém estatísticas do log
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_sessions' => count($this->rollbackLog['sessions']),
            'active_sessions' => 0,
            'completed_sessions' => 0,
            'failed_sessions' => 0,
            'total_files_created' => 0,
            'total_files_modified' => 0,
            'total_directories_created' => 0,
            'domains' => [],
            'oldest_session' => null,
            'newest_session' => null,
        ];

        foreach ($this->rollbackLog['sessions'] as $session) {
            // Contadores por status
            switch ($session['status']) {
                case 'active':
                    $stats['active_sessions']++;
                    break;
                case 'completed':
                    $stats['completed_sessions']++;
                    break;
                case 'failed':
                    $stats['failed_sessions']++;
                    break;
            }

            // Contadores de arquivos
            $stats['total_files_created'] += count($session['created'] ?? []);
            $stats['total_files_modified'] += count($session['modified'] ?? []);
            $stats['total_directories_created'] += count($session['directories'] ?? []);

            // Domínios
            if (! in_array($session['domain'], $stats['domains'])) {
                $stats['domains'][] = $session['domain'];
            }

            // Datas
            $sessionDate = $session['timestamp'];
            if (! $stats['oldest_session'] || $sessionDate < $stats['oldest_session']) {
                $stats['oldest_session'] = $sessionDate;
            }
            if (! $stats['newest_session'] || $sessionDate > $stats['newest_session']) {
                $stats['newest_session'] = $sessionDate;
            }
        }

        return $stats;
    }

    /**
     * Salva o log no disco
     */
    public function saveLog(): void
    {
        $dir = dirname($this->rollbackLogPath);
        if (! file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->rollbackLogPath,
            json_encode($this->rollbackLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Obtém o usuário atual
     */
    private function getCurrentUser(): string
    {
        if (Auth::check()) {
            return Auth::user()->name ?? Auth::user()->email ?? 'authenticated_user';
        }

        // Tentar obter usuário do sistema
        $user = get_current_user();
        if ($user) {
            return $user;
        }

        // Fallback
        return 'unknown_user';
    }

    /**
     * Exporta o log para array (compatibilidade com sistema antigo)
     */
    public function exportLegacyFormat(): array
    {
        $legacy = [
            'created' => [],
            'modified' => [],
            'directories' => [],
        ];

        foreach ($this->rollbackLog['sessions'] as $session) {
            $legacy['created'] = array_merge($legacy['created'], $session['created'] ?? []);
            $legacy['modified'] = array_merge($legacy['modified'], $session['modified'] ?? []);
            $legacy['directories'] = array_merge($legacy['directories'], $session['directories'] ?? []);
        }

        return $legacy;
    }
}
