<?php

namespace App\Console\Commands\Generator\Utils;

use Illuminate\Support\Facades\File;

class TemplateManager
{
    public function processStub(string $stubName, array $replacements): string
    {
        $stubPath = app_path('Domains/Shared/Stubs/' . $stubName);

        if (!File::exists($stubPath)) {
            throw new \Exception("Stub não encontrado: {$stubPath}");
        }

        $content = File::get($stubPath);

        // Adicionar suporte para novos placeholders
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        // Verificar se há placeholders não substituídos
        if (preg_match('/{{.*?}}/', $content)) {
            throw new \Exception("Existem placeholders não substituídos no stub: {$stubName}");
        }

        return $content;
    }

    public function customizeStubs(?string $stubsPath): void
    {
        $defaultPath = app_path('Domains/Shared/Stubs/');
        $stubsPath = $stubsPath ?? $defaultPath;

        if (!File::exists($stubsPath)) {
            throw new \Exception("Diretório de stubs não encontrado: {$stubsPath}");
        }

        // Implementação para carregar templates personalizados
        $customStubs = File::files($stubsPath);

        foreach ($customStubs as $stub) {
            $this->stubs[] = $stub->getFilename();
        }
    }

    public function getAvailableStubs(): array
    {
        $backendStubs = File::files(app_path('Domains/Shared/Stubs/BackEnd'));
        $frontendStubs = File::files(app_path('Domains/Shared/Stubs/FrontEnd'));

        $stubs = [];

        foreach ($backendStubs as $stub) {
            $stubs['BackEnd'][] = $stub->getFilename();
        }

        foreach ($frontendStubs as $stub) {
            $stubs['FrontEnd'][] = $stub->getFilename();
        }

        return $stubs;
    }
}
