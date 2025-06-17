<?php

namespace App\Console\Commands\Generator\Generators\Utils;

use App\Console\Commands\Generator\Generators\FrontEnd\FrontendPathTrait;
use Illuminate\Support\Facades\File;

class FrontendUtils
{
    use FrontendPathTrait;

    /**
     * Adiciona um novo menu ao arquivo TypeScript especificado.
     *
     * @param  string  $filePath  Caminho do arquivo TypeScript onde o menu será adicionado.
     * @param  string  $newMenu  O novo menu a ser adicionado.
     * @return void
     */
    public function addMenu($config)
    {
        $pathMenu = $this->getFrontendPath().'/navigation/vertical/index.ts';

        $domain = $config['domain'];

        $menu = "{
                    title: '$domain',
                    icon: { icon: 'tabler-template' },
                    to: '".str($domain)->snake('-')."',
                    action: 'list',
                    subject: '".str($domain)->snake('-')."',
                },";

        // Verifica se o arquivo existe
        if (File::exists($pathMenu)) {
            $codigo = File::get($pathMenu); // Obtenha o conteúdo do arquivo TypeScript

            // Verifica se o novo menu já está presente no código
            if (strpos($codigo, $menu) === false) {
                // Adiciona o novo menu ao final do código
                $novoCodigo = rtrim($codigo, "] \r\n")."\n".$menu."\n]";

                // Atualize o arquivo TypeScript com o novo código
                File::put($pathMenu, $novoCodigo);
            }
        }

        return true;
    }
}
