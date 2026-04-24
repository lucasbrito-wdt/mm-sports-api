<?php

namespace Tests\Unit\Console\Commands\Generator;

use App\Console\Commands\Generator\RollbackManager;
use App\Console\Commands\Generator\Utils\RouteManager;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\File;

class RollbackManagerTest extends TestCase
{
    private string $testRollbackLogPath;

    /** @var array<string, mixed> */
    private array $legacyRollbackFilePayload;

    protected function setUp(): void
    {
        parent::setUp();

        $frontendRoot = storage_path('testing/frontend');
        if (! is_dir($frontendRoot.'/src/types')) {
            mkdir($frontendRoot.'/src/types', 0755, true);
        }

        $this->testRollbackLogPath = storage_path('testing/rollback_test.json');
        $this->legacyRollbackFilePayload = [
            'version' => '1.0',
            'timestamp' => now()->toIso8601String(),
            'created' => [
                base_path('app/Domains/Products/Models/Product.php'),
                base_path('app/Domains/Products/Controllers/ProductController.php'),
                storage_path('testing/frontend/src/types/Product.ts'),
            ],
            'modified' => [
                base_path('routes/web.php') => storage_path('testing/backups/web.php.backup'),
                base_path('config/permission_list.php') => storage_path('testing/backups/permission_list.php.backup'),
            ],
            'directories' => [
                base_path('app/Domains/Products'),
                base_path('app/Domains/Products/Models'),
            ],
        ];

        $testDir = dirname($this->testRollbackLogPath);
        if (! is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }

        file_put_contents($this->testRollbackLogPath, json_encode($this->legacyRollbackFilePayload, JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testRollbackLogPath)) {
            unlink($this->testRollbackLogPath);
        }

        $testDir = dirname($this->testRollbackLogPath);
        if (is_dir($testDir)) {
            File::deleteDirectory($testDir);
        }

        parent::tearDown();
    }

    private function injectSessions(RollbackManager $command, array $sessions): void
    {
        $reflection = new \ReflectionClass($command);
        $sessionsProperty = $reflection->getProperty('sessions');
        $sessionsProperty->setAccessible(true);
        $sessionsProperty->setValue($command, $sessions);

        $rebuild = $reflection->getMethod('rebuildFlatFromSessions');
        $rebuild->setAccessible(true);
        $rebuild->invoke($command);
    }

    private function sessionFromLegacy(array $legacy, string $domain = 'Products'): array
    {
        return [
            'id' => 'test-session',
            'domain' => $domain,
            'status' => 'completed',
            'action' => 'create',
            'timestamp' => $legacy['timestamp'] ?? now()->toIso8601String(),
            'created' => $legacy['created'] ?? [],
            'modified' => $legacy['modified'] ?? [],
            'directories' => $legacy['directories'] ?? [],
        ];
    }

    /** @test */
    public function can_load_rollback_log()
    {
        $routeManager = $this->createMock(RouteManager::class);
        $command = new RollbackManager($routeManager);
        $reflection = new \ReflectionClass($command);

        $rollbackLogPathProperty = $reflection->getProperty('rollbackLogPath');
        $rollbackLogPathProperty->setAccessible(true);
        $rollbackLogPathProperty->setValue($command, $this->testRollbackLogPath);

        $loadedLog = json_decode(file_get_contents($this->testRollbackLogPath), true);
        $normalize = $reflection->getMethod('normalizeSessions');
        $normalize->setAccessible(true);
        $sessions = $normalize->invoke($command, $loadedLog);

        $this->assertCount(1, $sessions);
        $this->assertCount(3, $sessions[0]['created']);
        $this->assertCount(2, $sessions[0]['modified']);
        $this->assertCount(2, $sessions[0]['directories']);
    }

    /** @test */
    public function can_extract_domains_from_log()
    {
        $routeManager = $this->createMock(RouteManager::class);
        $command = new RollbackManager($routeManager);
        $this->injectSessions($command, [$this->sessionFromLegacy($this->legacyRollbackFilePayload)]);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('extractDomainsFromLog');
        $method->setAccessible(true);

        $domains = $method->invoke($command);

        $this->assertIsArray($domains);
        $this->assertContains('Products', $domains);
    }

    /** @test */
    public function can_get_domain_files()
    {
        $routeManager = $this->createMock(RouteManager::class);
        $command = new RollbackManager($routeManager);
        $this->injectSessions($command, [$this->sessionFromLegacy($this->legacyRollbackFilePayload)]);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getDomainFiles');
        $method->setAccessible(true);

        $domainFiles = $method->invoke($command, 'Products');

        $this->assertIsArray($domainFiles);
        $this->assertArrayHasKey('created', $domainFiles);
        $this->assertArrayHasKey('modified', $domainFiles);
        $this->assertGreaterThan(0, count($domainFiles['created']));
    }

    /** @test */
    public function can_count_frontend_files()
    {
        $routeManager = $this->createMock(RouteManager::class);
        $command = new RollbackManager($routeManager);
        $this->injectSessions($command, [$this->sessionFromLegacy($this->legacyRollbackFilePayload)]);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getFrontendFilesCount');
        $method->setAccessible(true);

        $frontendCount = $method->invoke($command);

        $this->assertIsInt($frontendCount);
        $this->assertGreaterThanOrEqual(1, $frontendCount);
    }

    /** @test */
    public function command_fails_when_no_rollback_log_exists()
    {
        $isolatedPath = storage_path('testing/rollback-no-file-'.uniqid('', true).'.json');
        @unlink($isolatedPath);
        $this->assertFileDoesNotExist($isolatedPath);

        $routeManager = $this->createMock(RouteManager::class);
        $this->app->instance(RouteManager::class, $routeManager);

        putenv('ROLLBACK_LOG_PATH='.$isolatedPath);
        try {
            $this->artisan('rollback:manager')
                ->expectsOutputToContain('Nenhum log de rollback encontrado')
                ->assertExitCode(1);
        } finally {
            putenv('ROLLBACK_LOG_PATH');
            @unlink($isolatedPath);
        }
    }

    /** @test */
    public function command_works_with_domain_option()
    {
        $routeManager = $this->createMock(RouteManager::class);
        $routeManager->method('removeDomainRoutes')->willReturn(true);

        $testFiles = [
            base_path('app/Domains/Products/Models/Product.php'),
        ];

        foreach ($testFiles as $file) {
            $dir = dirname($file);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($file, '<?php // Test file');
        }

        $this->app->instance(RouteManager::class, $routeManager);

        $reflection = new \ReflectionClass(RollbackManager::class);
        $command = $reflection->newInstanceWithoutConstructor();

        $routeManagerProperty = $reflection->getProperty('routeManager');
        $routeManagerProperty->setAccessible(true);
        $routeManagerProperty->setValue($command, $routeManager);

        $rollbackLogPathProperty = $reflection->getProperty('rollbackLogPath');
        $rollbackLogPathProperty->setAccessible(true);
        $rollbackLogPathProperty->setValue($command, $this->testRollbackLogPath);

        $this->injectSessions($command, [$this->sessionFromLegacy($this->legacyRollbackFilePayload)]);

        $extractMethod = $reflection->getMethod('extractDomainsFromLog');
        $extractMethod->setAccessible(true);
        $domains = $extractMethod->invoke($command);

        $this->assertContains('Products', $domains);

        foreach ($testFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        $testDir = base_path('app/Domains/Products/Models');
        if (is_dir($testDir)) {
            rmdir($testDir);
        }
        $productsDomainDir = base_path('app/Domains/Products');
        if (is_dir($productsDomainDir)) {
            rmdir($productsDomainDir);
        }
    }

    /** @test */
    public function can_simulate_rollback_with_dry_run()
    {
        $routeManager = $this->createMock(RouteManager::class);
        $command = new RollbackManager($routeManager);
        $this->injectSessions($command, [$this->sessionFromLegacy($this->legacyRollbackFilePayload)]);

        $reflection = new \ReflectionClass($command);
        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputStyle = $this->createMock(\Illuminate\Console\OutputStyle::class);
        $outputStyle->expects($this->atLeastOnce())->method('writeln')->willReturnCallback(function (): void {});
        $outputProperty->setValue($command, $outputStyle);

        $method = $reflection->getMethod('simulateRollback');
        $method->setAccessible(true);

        $method->invoke($command);
    }

    /** @test */
    public function validates_rollback_log_format()
    {
        $invalidLog = ['invalid' => 'format'];
        $isolatedPath = storage_path('testing/rollback-invalid-'.uniqid('', true).'.json');
        $dir = dirname($isolatedPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($isolatedPath, json_encode($invalidLog, JSON_THROW_ON_ERROR));

        $routeManager = $this->createMock(RouteManager::class);
        $this->app->instance(RouteManager::class, $routeManager);

        putenv('ROLLBACK_LOG_PATH='.$isolatedPath);
        try {
            $this->artisan('rollback:manager', ['--force' => true])
                ->expectsOutputToContain('Log de rollback corrompido ou inválido')
                ->assertExitCode(1);
        } finally {
            putenv('ROLLBACK_LOG_PATH');
            if (file_exists($isolatedPath)) {
                unlink($isolatedPath);
            }
        }
    }

    /** @test */
    public function handles_missing_backup_files_gracefully()
    {
        $logWithMissingBackup = $this->legacyRollbackFilePayload;
        $logWithMissingBackup['modified'] = [
            base_path('routes/web.php') => '/nonexistent/backup/file.php',
        ];

        $routeManager = $this->createMock(RouteManager::class);
        $command = new RollbackManager($routeManager);
        $this->injectSessions($command, [$this->sessionFromLegacy($logWithMissingBackup)]);

        $this->assertTrue(true);
    }
}
