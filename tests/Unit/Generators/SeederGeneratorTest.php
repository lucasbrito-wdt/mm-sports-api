<?php

namespace Tests\Unit\Generators;

use App\Console\Commands\Generator\Generators\BackEnd\SeederGenerator;
use App\Console\Commands\Generator\Utils\TemplateManager;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class SeederGeneratorTest extends TestCase
{
    protected $templateManager;
    protected $seederGenerator;
    protected $stubContent = '<?php

namespace {{namespace}};

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class {{seederName}} extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
    }
}';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock do TemplateManager
        $this->templateManager = Mockery::mock(TemplateManager::class);
        
        // Configurar o mock para retornar o conteúdo do stub
        $this->templateManager->shouldReceive('processStub')
            ->with('BackEnd/seeder.stub', Mockery::any())
            ->andReturn($this->stubContent);
            
        // Mock do File para não afetar o sistema de arquivos real durante os testes
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('makeDirectory')->andReturn(true);
        File::shouldReceive('put')->andReturn(true);
        File::shouldReceive('get')->andReturn('<?php namespace Database\Seeders; class DatabaseSeeder {}');
        
        // Instanciar o gerador com o mock
        $this->seederGenerator = new SeederGenerator($this->templateManager);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    public function testGenerateCreatesSeederFile()
    {
        // Configuração para o teste
        $config = [
            'domain' => 'TestDomain',
            'model' => 'TestModel',
        ];
        
        // Verificar que o File::put é chamado com os parâmetros corretos
        File::shouldReceive('put')
            ->with(app_path("Domains/TestDomain/Seeders/TestModelSeeder.php"), Mockery::any())
            ->once()
            ->andReturn(true);
        
        // Executar o método
        $result = $this->seederGenerator->generate($config);
        
        // Verificar o resultado
        $this->assertTrue($result);
    }
    
    public function testSeederIsRegisteredInDatabaseSeeder()
    {
        // Configuração para o teste
        $config = [
            'domain' => 'TestDomain',
            'model' => 'TestModel',
        ];
        
        // Verificar que File::put é chamado para o DatabaseSeeder
        File::shouldReceive('put')
            ->with(database_path('seeders/DatabaseSeeder.php'), Mockery::any())
            ->once()
            ->andReturn(true);
            
        // Executar o método
        $result = $this->seederGenerator->generate($config);
        
        // Verificar o resultado
        $this->assertTrue($result);
    }
}
