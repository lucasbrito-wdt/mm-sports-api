<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class MigrationServiceProvider extends ServiceProvider
{
    public function register()
    {
        // register logic here
    }

    public function boot(): void
    {
        $this->loadAllMigrations();
    }

    private function getMigrationFiles(): array
    {
        $dirName = app_path()
            . DIRECTORY_SEPARATOR . "Domains"
            . DIRECTORY_SEPARATOR . "*"
            . DIRECTORY_SEPARATOR . "Migrations"
            . DIRECTORY_SEPARATOR . "*.php";
        return glob($dirName, GLOB_BRACE);
    }

    private function loadAllMigrations(): void
    {
        $files = $this->getMigrationFiles();
        foreach ($files as $file) {
            $this->loadMigrationsFrom($file);
        }
    }
}
