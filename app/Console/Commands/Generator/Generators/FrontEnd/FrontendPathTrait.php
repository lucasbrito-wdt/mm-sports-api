<?php

namespace App\Console\Commands\Generator\Generators\FrontEnd;

use Illuminate\Support\Facades\File;

trait FrontendPathTrait
{
    /**
     * Obter o caminho absoluto do diretório frontend
     */
    private function getFrontendPath(): string
    {
        $basePath = base_path();
        $projectDir = env('CDF_DIR_FRONT_END');

        // Construir o caminho correto
        if (str_starts_with($projectDir, '..')) {
            // Caminho relativo como "../template-exemple"
            $frontEndPath = dirname($basePath) . DIRECTORY_SEPARATOR . ltrim($projectDir, './\\');
        } else {
            // Caminho absoluto ou relativo simples
            $frontEndPath = $projectDir;
        }

        $frontEndAbsoluteDir = realpath($frontEndPath);

        // Se realpath falhar, usar o caminho direto
        if (!$frontEndAbsoluteDir) {
            $frontEndAbsoluteDir = $frontEndPath;
        }

        // Normalizar o caminho
        $frontEndAbsoluteDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $frontEndAbsoluteDir);

        // Verificar se o diretório existe
        if (!is_dir($frontEndAbsoluteDir)) {
            throw new \Exception("Frontend directory not found: {$frontEndAbsoluteDir}");
        }

        return $frontEndAbsoluteDir;
    }
}
