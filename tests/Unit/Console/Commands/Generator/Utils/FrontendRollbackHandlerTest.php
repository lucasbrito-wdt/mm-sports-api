<?php

namespace Tests\Unit\Console\Commands\Generator\Utils;

use App\Console\Commands\Generator\Utils\FrontendRollbackHandler;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\File;
use Tests\CreatesApplication;

class FrontendRollbackHandlerTest extends TestCase
{
    use CreatesApplication;

    private FrontendRollbackHandler $handler;
    private string $testFrontendPath;
    private Command $mockCommand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockCommand = $this->createMock(Command::class);
        $this->handler = new FrontendRollbackHandler($this->mockCommand);

        // Criar diretório temporário para testes
        $this->testFrontendPath = storage_path('testing/frontend');
        if (!is_dir($this->testFrontendPath)) {
            mkdir($this->testFrontendPath, 0755, true);
        }

        // Configurar estrutura básica de teste
        $this->setupTestFrontendStructure();
    }

    protected function tearDown(): void
    {
        // Limpar arquivos de teste
        if (is_dir($this->testFrontendPath)) {
            File::deleteDirectory(dirname($this->testFrontendPath));
        }

        parent::tearDown();
    }

    /** @test */
    public function can_identify_frontend_files()
    {
        $frontendFiles = [
            $this->testFrontendPath . '/src/components/TestComponent.vue',
            $this->testFrontendPath . '/src/stores/TestStore.ts',
            $this->testFrontendPath . '/src/types/TestTypes.ts',
        ];

        $backendFiles = [
            base_path('app/Models/Test.php'),
            base_path('app/Controllers/TestController.php'),
        ];

        // Usar reflexão para testar método privado
        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('isFrontendFile');
        $method->setAccessible(true);

        foreach ($frontendFiles as $file) {
            $this->assertTrue($method->invoke($this->handler, $file), "Should identify {$file} as frontend file");
        }

        foreach ($backendFiles as $file) {
            $this->assertFalse($method->invoke($this->handler, $file), "Should not identify {$file} as frontend file");
        }
    }

    /** @test */
    public function can_rollback_frontend_files()
    {
        // Criar arquivos de teste
        $testFiles = [
            $this->testFrontendPath . '/src/components/TestComponent.vue',
            $this->testFrontendPath . '/src/stores/ProductStore.ts',
            $this->testFrontendPath . '/src/types/Product.ts',
        ];

        foreach ($testFiles as $file) {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($file, $this->getTestFileContent(basename($file)));
        }

        // Executar rollback
        $results = $this->handler->rollbackFrontendFiles($testFiles);

        // Verificar resultados
        $this->assertArrayHasKey('success', $results);
        $this->assertArrayHasKey('failed', $results);
        $this->assertArrayHasKey('stores_updated', $results);
        $this->assertArrayHasKey('types_updated', $results);

        $this->assertCount(3, $results['success']);
        $this->assertCount(0, $results['failed']);
        $this->assertCount(1, $results['stores_updated']);
        $this->assertCount(1, $results['types_updated']);

        // Verificar se arquivos foram removidos
        foreach ($testFiles as $file) {
            $this->assertFileDoesNotExist($file);
        }
    }

    /** @test */
    public function can_extract_store_name_from_path()
    {
        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('extractStoreNameFromPath');
        $method->setAccessible(true);

        $testCases = [
            '/path/to/stores/ProductStore.ts' => 'ProductStore',
            '/path/to/stores/userStore.ts' => 'UserStore',
            '/path/to/stores/categoryStore.ts' => 'CategoryStore',
            '/path/to/components/Product.vue' => null,
        ];

        foreach ($testCases as $path => $expected) {
            $result = $method->invoke($this->handler, $path);
            $this->assertEquals($expected, $result, "Failed for path: {$path}");
        }
    }

    /** @test */
    public function can_extract_type_name_from_path()
    {
        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('extractTypeNameFromPath');
        $method->setAccessible(true);

        $testCases = [
            '/path/to/types/Product.ts' => 'Product',
            '/path/to/types/User.ts' => 'User',
            '/path/to/types/Category.ts' => 'Category',
            '/path/to/components/Product.vue' => null,
        ];

        foreach ($testCases as $path => $expected) {
            $result = $method->invoke($this->handler, $path);
            $this->assertEquals($expected, $result, "Failed for path: {$path}");
        }
    }

    /** @test */
    public function can_extract_route_name_from_path()
    {
        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('extractRouteNameFromPath');
        $method->setAccessible(true);

        $testCases = [
            '/path/to/pages/ProductList.vue' => 'ProductList',
            '/path/to/views/UserProfile.vue' => 'UserProfile',
            '/path/to/components/Header.vue' => null,
        ];

        foreach ($testCases as $path => $expected) {
            $result = $method->invoke($this->handler, $path);
            $this->assertEquals($expected, $result, "Failed for path: {$path}");
        }
    }

    /** @test */
    public function can_verify_frontend_integrity()
    {
        // Criar arquivos críticos
        $criticalFiles = [
            $this->testFrontendPath . '/package.json',
            $this->testFrontendPath . '/src/main.ts',
            $this->testFrontendPath . '/src/App.vue',
        ];

        foreach ($criticalFiles as $file) {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (str_ends_with($file, '.json')) {
                file_put_contents($file, '{"name": "test-project"}');
            } else {
                file_put_contents($file, $this->getTestFileContent(basename($file)));
            }
        }

        $issues = $this->handler->verifyFrontendIntegrity();
        $this->assertIsArray($issues);
    }

    /** @test */
    public function can_generate_rollback_report()
    {
        $testResults = [
            'success' => ['file1.vue', 'file2.ts'],
            'failed' => [
                ['file' => 'file3.ts', 'error' => 'Permission denied']
            ],
            'stores_updated' => ['ProductStore.ts'],
            'types_updated' => ['Product.ts'],
            'routes_updated' => []
        ];

        $report = $this->handler->generateFrontendRollbackReport($testResults);

        $this->assertIsString($report);
        $this->assertStringContainsString('RELATÓRIO DE ROLLBACK - FRONTEND', $report);
        $this->assertStringContainsString('Arquivos removidos com sucesso: 2', $report);
        $this->assertStringContainsString('Falhas ao remover arquivos: 1', $report);
        $this->assertStringContainsString('Stores atualizadas: 1', $report);
        $this->assertStringContainsString('Permission denied', $report);
    }

    /** @test */
    public function handles_nonexistent_files_gracefully()
    {
        $nonExistentFiles = [
            '/path/to/nonexistent/file1.vue',
            '/path/to/nonexistent/file2.ts',
        ];

        $results = $this->handler->rollbackFrontendFiles($nonExistentFiles);

        // Arquivos não existentes devem ser considerados como sucesso
        $this->assertCount(2, $results['success']);
        $this->assertCount(0, $results['failed']);
    }

    private function setupTestFrontendStructure(): void
    {
        $directories = [
            $this->testFrontendPath . '/src',
            $this->testFrontendPath . '/src/components',
            $this->testFrontendPath . '/src/stores',
            $this->testFrontendPath . '/src/types',
            $this->testFrontendPath . '/src/pages',
            $this->testFrontendPath . '/src/router',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Criar arquivos básicos
        $basicFiles = [
            $this->testFrontendPath . '/package.json' => '{"name": "test-frontend"}',
            $this->testFrontendPath . '/src/main.ts' => 'import { createApp } from "vue";',
            $this->testFrontendPath . '/src/App.vue' => '<template><div>Test App</div></template>',
            $this->testFrontendPath . '/src/stores/index.ts' => 'export * from "./ProductStore";',
            $this->testFrontendPath . '/src/types/index.ts' => 'export * from "./Product";',
            $this->testFrontendPath . '/src/router/index.ts' => 'import { createRouter } from "vue-router";',
        ];

        foreach ($basicFiles as $file => $content) {
            file_put_contents($file, $content);
        }
    }

    private function getTestFileContent(string $filename): string
    {
        if (str_ends_with($filename, '.vue')) {
            return '<template><div>Test Component</div></template><script setup lang="ts"></script>';
        }

        if (str_ends_with($filename, '.ts')) {
            return 'export interface TestInterface { id: number; }';
        }

        return '// Test file content';
    }
}
