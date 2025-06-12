<?php

namespace Tests\Unit\Console\Commands\Generator;

use App\Console\Commands\Generator\RollbackStatus;
use App\Console\Commands\Generator\Utils\RollbackLogger;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

class RollbackStatusTest extends TestCase
{
    use CreatesApplication;

    private RollbackLogger $mockLogger;
    private array $testSessions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testSessions = [
            'session1' => [
                'id' => 'session1',
                'domain' => 'Products',
                'model' => 'Product',
                'action' => 'create',
                'status' => 'completed',
                'timestamp' => '2024-01-15T10:30:00Z',
                'created' => [
                    'app/Domains/Products/Models/Product.php',
                    'app/Domains/Products/Controllers/ProductController.php',
                ],
                'modified' => [
                    'routes/web.php',
                    'config/permission_list.php',
                ],
                'directories' => [
                    'app/Domains/Products',
                ],
                'metadata' => [
                    'user' => 'test_user',
                    'version' => '2.0',
                ]
            ],
            'session2' => [
                'id' => 'session2',
                'domain' => 'Categories',
                'model' => 'Category',
                'action' => 'create',
                'status' => 'completed',
                'timestamp' => '2024-01-15T11:00:00Z',
                'created' => [
                    'app/Domains/Categories/Models/Category.php',
                ],
                'modified' => [],
                'directories' => [
                    'app/Domains/Categories',
                ],
                'metadata' => [
                    'user' => 'test_user',
                    'version' => '2.0',
                ]
            ]
        ];

        $this->mockLogger = $this->createMock(RollbackLogger::class);
    }

    /** @test */
    public function displays_general_statistics()
    {
        $statistics = [
            'total_sessions' => 2,
            'total_files_created' => 3,
            'total_files_modified' => 2,
            'domains' => ['Products', 'Categories'],
            'latest_session' => '2024-01-15T11:00:00Z',
            'oldest_session' => '2024-01-15T10:30:00Z',
        ];

        $this->mockLogger
            ->method('getStatistics')
            ->willReturn($statistics);

        $this->mockLogger
            ->method('getSessions')
            ->willReturn($this->testSessions);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status')
            ->expectsOutput('📊 Status do Sistema de Rollback')
            ->expectsOutput('Total de sessões: 2')
            ->expectsOutput('Arquivos criados: 3')
            ->expectsOutput('Arquivos modificados: 2')
            ->expectsOutput('Domínios únicos: 2')
            ->assertExitCode(0);
    }

    /** @test */
    public function displays_sessions_by_domain()
    {
        $this->mockLogger
            ->method('getStatistics')
            ->willReturn([
                'total_sessions' => 2,
                'total_files_created' => 3,
                'total_files_modified' => 2,
                'domains' => ['Products', 'Categories'],
            ]);

        $this->mockLogger
            ->method('getSessions')
            ->willReturn($this->testSessions);

        $this->mockLogger
            ->method('getSessionsByDomain')
            ->with('Products')
            ->willReturn(['session1' => $this->testSessions['session1']]);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--domain' => 'Products'])
            ->expectsOutput('🎯 Sessões do Domínio: Products')
            ->expectsOutput('📋 Sessão: session1')
            ->expectsOutput('Modelo: Product')
            ->assertExitCode(0);
    }

    /** @test */
    public function displays_specific_session_details()
    {
        $this->mockLogger
            ->method('getSession')
            ->with('session1')
            ->willReturn($this->testSessions['session1']);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--session' => 'session1'])
            ->expectsOutput('📋 Detalhes da Sessão: session1')
            ->expectsOutput('Domínio: Products')
            ->expectsOutput('Modelo: Product')
            ->expectsOutput('Status: completed')
            ->expectsOutput('Usuário: test_user')
            ->assertExitCode(0);
    }

    /** @test */
    public function handles_nonexistent_session()
    {
        $this->mockLogger
            ->method('getSession')
            ->with('nonexistent')
            ->willReturn(null);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--session' => 'nonexistent'])
            ->expectsOutput('❌ Sessão \'nonexistent\' não encontrada.')
            ->assertExitCode(1);
    }

    /** @test */
    public function handles_nonexistent_domain()
    {
        $this->mockLogger
            ->method('getSessionsByDomain')
            ->with('NonexistentDomain')
            ->willReturn([]);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--domain' => 'NonexistentDomain'])
            ->expectsOutput('❌ Nenhuma sessão encontrada para o domínio \'NonexistentDomain\'.')
            ->assertExitCode(1);
    }

    /** @test */
    public function displays_json_output()
    {
        $statistics = [
            'total_sessions' => 2,
            'total_files_created' => 3,
            'total_files_modified' => 2,
            'domains' => ['Products', 'Categories'],
        ];

        $this->mockLogger
            ->method('getStatistics')
            ->willReturn($statistics);

        $this->mockLogger
            ->method('getSessions')
            ->willReturn($this->testSessions);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--json' => true])
            ->expectsOutputToContain('"total_sessions": 2')
            ->expectsOutputToContain('"domains": ["Products", "Categories"]')
            ->assertExitCode(0);
    }

    /** @test */
    public function displays_table_format()
    {
        $this->mockLogger
            ->method('getStatistics')
            ->willReturn([
                'total_sessions' => 2,
                'total_files_created' => 3,
                'total_files_modified' => 2,
                'domains' => ['Products', 'Categories'],
            ]);

        $this->mockLogger
            ->method('getSessions')
            ->willReturn($this->testSessions);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--table' => true])
            ->expectsOutput('📊 Sessões de Rollback')
            ->assertExitCode(0);
    }

    /** @test */
    public function handles_empty_log()
    {
        $this->mockLogger
            ->method('getStatistics')
            ->willReturn([
                'total_sessions' => 0,
                'total_files_created' => 0,
                'total_files_modified' => 0,
                'domains' => [],
            ]);

        $this->mockLogger
            ->method('getSessions')
            ->willReturn([]);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status')
            ->expectsOutput('📊 Status do Sistema de Rollback')
            ->expectsOutput('Total de sessões: 0')
            ->expectsOutput('ℹ️ Nenhuma sessão de rollback registrada.')
            ->assertExitCode(0);
    }

    /** @test */
    public function displays_session_file_counts_correctly()
    {
        $this->mockLogger
            ->method('getSession')
            ->with('session1')
            ->willReturn($this->testSessions['session1']);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--session' => 'session1'])
            ->expectsOutput('📁 Arquivos Criados (2):')
            ->expectsOutput('📝 Arquivos Modificados (2):')
            ->assertExitCode(0);
    }

    /** @test */
    public function limits_file_display_to_ten_items()
    {
        // Criar sessão com muitos arquivos
        $sessionWithManyFiles = $this->testSessions['session1'];
        $sessionWithManyFiles['created'] = [];

        // Adicionar 15 arquivos para testar o limite
        for ($i = 1; $i <= 15; $i++) {
            $sessionWithManyFiles['created'][] = "app/Test/File{$i}.php";
        }

        $this->mockLogger
            ->method('getSession')
            ->with('session1')
            ->willReturn($sessionWithManyFiles);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--session' => 'session1'])
            ->expectsOutput('📁 Arquivos Criados (15):')
            ->expectsOutput('  ... e mais 5 arquivos')
            ->assertExitCode(0);
    }

    /** @test */
    public function calculates_session_duration_when_available()
    {
        $sessionWithDuration = $this->testSessions['session1'];
        $sessionWithDuration['metadata']['duration'] = 45.5; // segundos

        $this->mockLogger
            ->method('getSession')
            ->with('session1')
            ->willReturn($sessionWithDuration);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--session' => 'session1'])
            ->expectsOutput('⏱️ Duração: 45.5s')
            ->assertExitCode(0);
    }

    /** @test */
    public function shows_session_warnings_when_present()
    {
        $sessionWithWarnings = $this->testSessions['session1'];
        $sessionWithWarnings['metadata']['warnings'] = [
            'Arquivo não encontrado: test.php',
            'Permissão negada: backup.php'
        ];

        $this->mockLogger
            ->method('getSession')
            ->with('session1')
            ->willReturn($sessionWithWarnings);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--session' => 'session1'])
            ->expectsOutput('⚠️ Avisos:')
            ->expectsOutput('  - Arquivo não encontrado: test.php')
            ->expectsOutput('  - Permissão negada: backup.php')
            ->assertExitCode(0);
    }
}
