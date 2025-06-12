<?php

namespace App\Console\Commands\Generator;

use App\Console\Commands\Generator\Utils\RollbackLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Console\Command\Command as CommandAlias;

class RollbackWebInterface extends Command
{
    protected $signature = 'rollback:web-interface {--port=8080} {--host=localhost}';
    protected $description = 'Inicia interface web para gerenciar rollbacks';

    private RollbackLogger $logger;

    public function __construct(RollbackLogger $logger)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    public function handle(): int
    {
        $host = $this->option('host');
        $port = $this->option('port');

        $this->info("🌐 Iniciando interface web de rollback...");
        $this->info("📍 Acesse: http://{$host}:{$port}/rollback");

        // Registrar rotas temporárias
        $this->registerWebRoutes();

        // Iniciar servidor
        $this->startWebServer($host, $port);

        return CommandAlias::SUCCESS;
    }

    private function registerWebRoutes(): void
    {
        // Rota principal da interface
        Route::get('/rollback', function () {
            return $this->renderRollbackInterface();
        });

        // API para dados do rollback
        Route::get('/rollback/api/sessions', function () {
            return response()->json($this->logger->getSessions());
        });

        Route::get('/rollback/api/statistics', function () {
            return response()->json($this->logger->getStatistics());
        });

        Route::post('/rollback/api/execute/{sessionId}', function ($sessionId) {
            return $this->executeRollbackSession($sessionId);
        });

        Route::delete('/rollback/api/session/{sessionId}', function ($sessionId) {
            $result = $this->logger->removeSession($sessionId);
            return response()->json(['success' => $result]);
        });

        Route::get('/rollback/api/session/{sessionId}', function ($sessionId) {
            $session = $this->logger->getSession($sessionId);
            return response()->json($session);
        });
    }

    private function renderRollbackInterface(): string
    {
        $statistics = $this->logger->getStatistics();
        $sessions = $this->logger->getSessions();

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Rollback - Laravel CRUD Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div id="app" class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fas fa-undo text-blue-600"></i> Gerenciador de Rollback
            </h1>
            <p class="text-gray-600">Laravel CRUD Generator - Interface de Rollback</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-list text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Total de Sessões</p>
                        <p class="text-2xl font-bold text-gray-800">{$statistics['total_sessions']}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-file text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Arquivos Criados</p>
                        <p class="text-2xl font-bold text-gray-800">{$statistics['total_files_created']}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-full">
                        <i class="fas fa-edit text-yellow-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Arquivos Modificados</p>
                        <p class="text-2xl font-bold text-gray-800">{$statistics['total_files_modified']}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-folder text-purple-600"></i>
                    </div>                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Domínios</p>
                        <p class="text-2xl font-bold text-gray-800">${count($statistics['domains'])}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sessions Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6 border-b">
                <h2 class="text-xl font-bold text-gray-800">Sessões de Geração</h2>
                <p class="text-gray-600">Gerencie as sessões de geração de CRUD</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domínio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ação</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Arquivos</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
HTML;

        foreach ($sessions as $session) {
            $statusClass = match ($session['status']) {
                'active' => 'bg-blue-100 text-blue-800',
                'completed' => 'bg-green-100 text-green-800',
                'failed' => 'bg-red-100 text-red-800',
                default => 'bg-gray-100 text-gray-800'
            };

            $createdCount = count($session['created'] ?? []);
            $modifiedCount = count($session['modified'] ?? []);
            $totalFiles = $createdCount + $modifiedCount;

            $formattedDate = date('d/m/Y H:i', strtotime($session['timestamp']));

            $HTML .= <<<HTML
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                {$session['id']}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{$session['domain']}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {$session['action']}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {$statusClass}">
                                    {$session['status']}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {$formattedDate}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex space-x-2">
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">
                                        +{$createdCount}
                                    </span>
                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs">
                                        ~{$modifiedCount}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button onclick="viewSession('{$session['id']}')"
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="executeRollback('{$session['id']}')"
                                            class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <button onclick="deleteSession('{$session['id']}')"
                                            class="text-gray-600 hover:text-gray-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
HTML;
        }

        $HTML .= <<<HTML
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-6 flex justify-between">
            <div class="space-x-2">
                <button onclick="executeFullRollback()"
                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md">
                    <i class="fas fa-undo"></i> Rollback Completo
                </button>
                <button onclick="clearAllSessions()"
                        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md">
                    <i class="fas fa-trash"></i> Limpar Todas as Sessões
                </button>
            </div>
            <div>
                <button onclick="refreshData()"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    <i class="fas fa-refresh"></i> Atualizar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal para detalhes da sessão -->
    <div id="sessionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
        <div class="flex justify-center items-center h-full">
            <div class="bg-white p-6 rounded-lg shadow-lg max-w-4xl w-full m-4 max-h-96 overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold">Detalhes da Sessão</h3>
                    <button onclick="closeModal()" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="sessionDetails"></div>
            </div>
        </div>
    </div>

    <script>
        async function viewSession(sessionId) {
            try {
                const response = await axios.get(`/rollback/api/session/\${sessionId}`);
                const session = response.data;

                let details = `
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div><strong>ID:</strong> \${session.id}</div>
                        <div><strong>Domínio:</strong> \${session.domain}</div>
                        <div><strong>Ação:</strong> \${session.action}</div>
                        <div><strong>Status:</strong> \${session.status}</div>
                        <div><strong>Data:</strong> \${new Date(session.timestamp).toLocaleString()}</div>
                        <div><strong>Usuário:</strong> \${session.user || 'N/A'}</div>
                    </div>
                `;

                if (session.created && session.created.length > 0) {
                    details += `
                        <h4 class="font-bold mb-2">Arquivos Criados (\${session.created.length}):</h4>
                        <ul class="text-sm mb-4 max-h-32 overflow-y-auto">
                            \${session.created.map(file => `<li class="text-green-600">+ \${file}</li>`).join('')}
                        </ul>
                    `;
                }

                if (session.modified && Object.keys(session.modified).length > 0) {
                    details += `
                        <h4 class="font-bold mb-2">Arquivos Modificados (\${Object.keys(session.modified).length}):</h4>
                        <ul class="text-sm mb-4 max-h-32 overflow-y-auto">
                            \${Object.keys(session.modified).map(file => `<li class="text-yellow-600">~ \${file}</li>`).join('')}
                        </ul>
                    `;
                }

                document.getElementById('sessionDetails').innerHTML = details;
                document.getElementById('sessionModal').classList.remove('hidden');
            } catch (error) {
                alert('Erro ao carregar detalhes da sessão');
            }
        }

        function closeModal() {
            document.getElementById('sessionModal').classList.add('hidden');
        }

        async function executeRollback(sessionId) {
            if (!confirm('Tem certeza que deseja fazer rollback desta sessão?')) return;

            try {
                const response = await axios.post(`/rollback/api/execute/\${sessionId}`);
                alert('Rollback executado com sucesso!');
                refreshData();
            } catch (error) {
                alert('Erro ao executar rollback');
            }
        }

        async function deleteSession(sessionId) {
            if (!confirm('Tem certeza que deseja deletar esta sessão?')) return;

            try {
                await axios.delete(`/rollback/api/session/\${sessionId}`);
                refreshData();
            } catch (error) {
                alert('Erro ao deletar sessão');
            }
        }

        function executeFullRollback() {
            if (!confirm('ATENÇÃO: Isto irá desfazer TODAS as alterações. Continuar?')) return;

            // Implementar chamada para rollback completo
            alert('Funcionalidade em desenvolvimento');
        }

        function clearAllSessions() {
            if (!confirm('Tem certeza que deseja limpar todas as sessões?')) return;

            // Implementar limpeza de todas as sessões
            alert('Funcionalidade em desenvolvimento');
        }

        function refreshData() {
            location.reload();
        }

        // Auto-refresh a cada 30 segundos
        setInterval(refreshData, 30000);
    </script>
</body>
</html>
HTML;

        return $HTML;
    }

    private function executeRollbackSession(string $sessionId)
    {
        try {
            $session = $this->logger->getSession($sessionId);

            if (!$session) {
                return response()->json(['error' => 'Session not found'], 404);
            }

            // Executar rollback da sessão específica
            $this->performSessionRollback($session);

            // Remover sessão do log
            $this->logger->removeSession($sessionId);

            return response()->json(['success' => true, 'message' => 'Rollback executado com sucesso']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function performSessionRollback(array $session): void
    {
        // Restaurar arquivos modificados
        foreach ($session['modified'] ?? [] as $file => $backup) {
            if (file_exists($backup)) {
                copy($backup, $file);
            }
        }

        // Remover arquivos criados
        foreach ($session['created'] ?? [] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // Remover diretórios vazios
        foreach (array_reverse($session['directories'] ?? []) as $dir) {
            if (is_dir($dir) && count(scandir($dir)) === 2) {
                rmdir($dir);
            }
        }
    }

    private function startWebServer(string $host, string $port): void
    {
        $command = "php -S {$host}:{$port} -t public";

        $this->info("🚀 Servidor iniciado!");
        $this->info("🔗 Acesse: http://{$host}:{$port}/rollback");
        $this->info("⏹️ Pressione Ctrl+C para parar");

        // Executar servidor
        passthru($command);
    }
}
