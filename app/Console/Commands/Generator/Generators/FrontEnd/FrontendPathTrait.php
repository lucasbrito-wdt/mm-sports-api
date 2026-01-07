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
                // Normalizar separadores de diretório
                $remainingPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $remainingPath);
                // Remover barra inicial se existir
                $remainingPath = ltrim($remainingPath, DIRECTORY_SEPARATOR);
                $frontEndPath = rtrim($currentPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $remainingPath;
            } else {
                $frontEndPath = $currentPath;
            }
        } else {
            // Caminho absoluto ou relativo simples
            $projectDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $projectDir);
            $projectDir = ltrim($projectDir, DIRECTORY_SEPARATOR);
            $frontEndPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $projectDir;
        }

        // Normalizar o caminho final (remover barras duplas, etc)
        $frontEndPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $frontEndPath);
        $frontEndPath = preg_replace('/[\/\\\\]+/', DIRECTORY_SEPARATOR, $frontEndPath);
        
        // Tentar resolver o caminho real se existir, senão usar o normalizado
        $realPath = realpath($frontEndPath);
        if ($realPath !== false) {
            $frontEndPath = $realPath;
        }

        // Verificar se o diretório existe
        if (!is_dir($frontEndPath)) {
            // Tentar encontrar o diretório com variações comuns do nome
            $parentDir = dirname($frontEndPath);
            $dirName = basename($frontEndPath);
            
            // Tentar variações: base-frontend, base_frontend, baseFrontend
            $variations = [
                $dirName,
                str_replace('_', '-', $dirName),
                str_replace('-', '_', $dirName),
            ];
            
            foreach ($variations as $variation) {
                $testPath = $parentDir . DIRECTORY_SEPARATOR . $variation;
                if (is_dir($testPath)) {
                    return $testPath;
                }
            }
            
            throw new \Exception("Frontend directory not found: {$frontEndPath}. Please check your CDF_DIR_FRONT_END environment variable.");
        }

        return $frontEndPath;
    }
}
