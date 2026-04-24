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
            
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('makeDirectory')->andReturn(true);
        File::shouldReceive('get')->andReturn(
            '<?php namespace Database\Seeders; use Illuminate\Database\Seeder; class DatabaseSeeder extends Seeder { public function run(): void { $this->call(Existing::class); } }'
        );
        
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
        $config = [
            'domain' => 'TestDomain',
            'model' => 'TestModel',
        ];

        File::shouldReceive('put')
            ->twice()
            ->andReturn(true);

        $result = $this->seederGenerator->generate($config);

        $this->assertTrue($result);
    }
    
    public function testSeederIsRegisteredInDatabaseSeeder()
    {
        $config = [
            'domain' => 'TestDomain',
            'model' => 'TestModel',
        ];

        File::shouldReceive('put')
            ->once()
            ->with(app_path('Domains/TestDomain/Seeders/TestModelSeeder.php'), Mockery::any())
            ->andReturn(true);

        File::shouldReceive('put')
            ->once()
            ->with(database_path('seeders/DatabaseSeeder.php'), Mockery::any())
            ->andReturn(true);

        $result = $this->seederGenerator->generate($config);

        $this->assertTrue($result);
    }
}
