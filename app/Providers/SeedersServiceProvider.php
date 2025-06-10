<?php

namespace App\Providers;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\ServiceProvider;

class SeedersServiceProvider extends ServiceProvider
{
    public function register()
    {
        // register logic here
    }

    public function boot(): void
    {
        $this->loadAllSeeders();
    }

    private function getSeedersFiles(): array
    {
        $dirName = app_path()
            . DIRECTORY_SEPARATOR . "App"
            . DIRECTORY_SEPARATOR . "Domains"
            . DIRECTORY_SEPARATOR . "*"
            . DIRECTORY_SEPARATOR . "Seeders"
            . DIRECTORY_SEPARATOR . "*.php";
        return glob($dirName, GLOB_BRACE);
    }

    protected function loadAllSeeders(): void
    {
        $seedList = $this->getSeedersFiles();

        $this->callAfterResolving(DatabaseSeeder::class, function (Seeder $seeder) use ($seedList) {
            foreach ($seedList as $seederPath) {
                // Converte o caminho para o namespace correto
                $class = $this->convertPathToNamespace($seederPath);

                try {
                    // Executa o seeder e exibe o status
                    $seeder->call($class);
                    echo "Seeder {$class} executado com sucesso.\n"; // Mostra que executou
                } catch (\Exception $exception) {
                    echo "Falha ao executar o seeder {$class}: {$exception->getMessage()}.\n"; // Mostra erro
                }
            }
        });
    }

    private function convertPathToNamespace(string $path): string
    {
        // Substitui o "app" por "App" para seguir os namespaces padrão do Laravel
        $namespace = str_replace(base_path('app'), '', $path);

        // Troca o separador de diretórios "\" ou "/" por "\\"
        $namespace = str_replace(['/', '\\'], '\\', $namespace);

        // Remove a extensão ".php"
        return rtrim($namespace, '.php');
    }
}
