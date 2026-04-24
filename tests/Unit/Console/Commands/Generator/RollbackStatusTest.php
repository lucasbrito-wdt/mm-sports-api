<?php

namespace Tests\Unit\Console\Commands\Generator;

use App\Console\Commands\Generator\Utils\RollbackLogger;
use Illuminate\Foundation\Testing\TestCase;

class RollbackStatusTest extends TestCase
{
    private RollbackLogger $mockLogger;

    /** @return array<string, mixed> */
    private function baseSession(string $id, string $domain): array
    {
        return [
            'id' => $id,
            'domain' => $domain,
            'action' => 'create',
            'status' => 'completed',
            'user' => 'test_user',
            'timestamp' => '2024-01-15T10:30:00Z',
            'created' => [
                base_path('app/Domains/Products/Models/Product.php'),
                base_path('app/Domains/Products/Controllers/ProductController.php'),
            ],
            'modified' => [
                base_path('routes/web.php') => storage_path('testing/backups/web.php.backup'),
                base_path('config/permission_list.php') => storage_path('testing/backups/permission.php.backup'),
            ],
            'directories' => [
                base_path('app/Domains/Products'),
            ],
            'metadata' => [
                'version' => '2.0',
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function sampleSessions(): array
    {
        $s1 = $this->baseSession('session1', 'Products');
        $s2 = $this->baseSession('session2', 'Categories');
        $s2['created'] = [base_path('app/Domains/Categories/Models/Category.php')];
        $s2['modified'] = [];
        $s2['directories'] = [base_path('app/Domains/Categories')];
        $s2['timestamp'] = '2024-01-15T11:00:00Z';

        return [$s1, $s2];
    }

    /** @return array<string, mixed> */
    private function fullStatisticsStub(): array
    {
        return [
            'total_sessions' => 2,
            'active_sessions' => 0,
            'completed_sessions' => 2,
            'failed_sessions' => 0,
            'total_files_created' => 3,
            'total_files_modified' => 2,
            'total_directories_created' => 2,
            'domains' => ['Products', 'Categories'],
            'oldest_session' => '2024-01-15T10:30:00Z',
            'newest_session' => '2024-01-15T11:00:00Z',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLogger = $this->createMock(RollbackLogger::class);
    }

    /** @test */
    public function displays_general_statistics()
    {
        $this->mockLogger
            ->method('getStatistics')
            ->willReturn($this->fullStatisticsStub());

        $this->mockLogger
            ->method('getSessions')
            ->willReturn($this->sampleSessions());

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status')
            ->expectsOutput('📊 Status do Sistema de Rollback')
            ->expectsOutputToContain('📈 Estatísticas Gerais:')
            ->expectsOutputToContain('Total de Sessões')
            ->assertExitCode(0);
    }

    /** @test */
    public function displays_sessions_by_domain()
    {
        $this->mockLogger
            ->method('getStatistics')
            ->willReturn($this->fullStatisticsStub());

        $this->mockLogger
            ->method('getSessions')
            ->willReturn($this->sampleSessions());

        $productsSession = $this->baseSession('session1', 'Products');
        $this->mockLogger
            ->method('getSessionsByDomain')
            ->with('Products')
            ->willReturn([$productsSession]);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--domain' => 'Products'])
            ->expectsOutputToContain('🏗️ Sessões do Domínio: Products')
            ->assertExitCode(0);
    }

    /** @test */
    public function displays_specific_session_details()
    {
        $sessions = $this->sampleSessions();

        $this->mockLogger
            ->method('getStatistics')
            ->willReturn($this->fullStatisticsStub());

        $this->mockLogger
            ->method('getSessions')
            ->willReturn($sessions);

        $this->mockLogger
            ->method('getSession')
            ->with('session1')
            ->willReturn($sessions[0]);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--session' => 'session1'])
            ->expectsOutputToContain('🔍 Detalhes da Sessão: session1')
            ->expectsOutputToContain('Products')
            ->assertExitCode(0);
    }

    /** @test */
    public function handles_nonexistent_session()
    {
        $this->mockLogger
            ->method('getStatistics')
            ->willReturn($this->fullStatisticsStub());

        $this->mockLogger
            ->method('getSessions')
            ->willReturn($this->sampleSessions());

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
            ->method('getStatistics')
            ->willReturn($this->fullStatisticsStub());

        $this->mockLogger
            ->method('getSessions')
            ->willReturn($this->sampleSessions());

        $this->mockLogger
            ->method('getSessionsByDomain')
            ->with('NonexistentDomain')
            ->willReturn([]);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--domain' => 'NonexistentDomain'])
            ->expectsOutputToContain('Nenhuma sessão encontrada para o domínio \'NonexistentDomain\'')
            ->assertExitCode(0);
    }

    /** @test */
    public function handles_empty_log()
    {
        $this->mockLogger
            ->method('getSessions')
            ->willReturn([]);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status')
            ->expectsOutput('📊 Status do Sistema de Rollback')
            ->expectsOutputToContain('Nenhuma sessão de rollback encontrada')
            ->assertExitCode(0);
    }

    /** @test */
    public function displays_session_file_counts_correctly()
    {
        $sessions = $this->sampleSessions();

        $this->mockLogger
            ->method('getStatistics')
            ->willReturn($this->fullStatisticsStub());

        $this->mockLogger
            ->method('getSessions')
            ->willReturn($sessions);

        $this->mockLogger
            ->method('getSession')
            ->with('session1')
            ->willReturn($sessions[0]);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--session' => 'session1'])
            ->expectsOutputToContain('📁 Arquivos Criados (2):')
            ->expectsOutputToContain('📝 Arquivos Modificados (2):')
            ->assertExitCode(0);
    }

    /** @test */
    public function limits_file_display_to_ten_items()
    {
        $sessionWithManyFiles = $this->baseSession('session1', 'Products');
        $sessionWithManyFiles['created'] = [];
        for ($i = 1; $i <= 15; $i++) {
            $sessionWithManyFiles['created'][] = base_path("app/Test/File{$i}.php");
        }

        $this->mockLogger
            ->method('getStatistics')
            ->willReturn($this->fullStatisticsStub());

        $this->mockLogger
            ->method('getSessions')
            ->willReturn([$sessionWithManyFiles]);

        $this->mockLogger
            ->method('getSession')
            ->with('session1')
            ->willReturn($sessionWithManyFiles);

        $this->app->instance(RollbackLogger::class, $this->mockLogger);

        $this->artisan('rollback:status', ['--session' => 'session1'])
            ->expectsOutputToContain('📁 Arquivos Criados (15):')
            ->expectsOutputToContain('... e mais 5 arquivos')
            ->assertExitCode(0);
    }
}
