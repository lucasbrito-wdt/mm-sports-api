<?php

namespace App\Console\Commands\Generator\Generators\FrontEnd;

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
            // Contar quantos níveis precisamos subir
            $levels = substr_count($projectDir, '../');

            // Começar do diretório base e subir os níveis necessários
            $currentPath = $basePath;
            for ($i = 0; $i < $levels; $i++) {
                $currentPath = dirname($currentPath);
            }

            // Remover todos os ../ do início e pegar apenas o resto do caminho
            $remainingPath = preg_replace('/^(\.\.\/)+/', '', $projectDir);

            // Se ainda houver caminho restante, adicionar
            if (!empty($remainingPath)) {
                $frontEndPath = $currentPath . str_replace('/', DIRECTORY_SEPARATOR, $remainingPath);
            } else {
                $frontEndPath = $currentPath;
            }
        } else {
            // Caminho absoluto ou relativo simples
            $frontEndPath = $basePath . DIRECTORY_SEPARATOR . $projectDir;
        }

        // Verificar se o diretório existe
        if (!is_dir($frontEndPath)) {
            throw new \Exception("Frontend directory not found: {$frontEndPath}");
        }

        return $frontEndPath;
    }
}
