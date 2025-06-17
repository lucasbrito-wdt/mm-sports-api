<?php

namespace App\Console\Commands\Generator\Generators\Utils;

use App\Console\Commands\Generator\Generators\FrontEnd\FrontendPathTrait;
use Coduo\PHPHumanizer\StringHumanizer as Humanize;
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
                    title: '".Humanize::humanize($domain)."',
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

    /**
     * Adiciona um novo subject ao array userSubjects no arquivo abilityConfig.ts.
     *
     * @param  array  $config  Configuração contendo o domínio.
     *
     * @throws \Exception
     */
    public function addAbility(array $config): bool
    {
        $abilityFile = $this->getFrontendPath().'/configs/abilityConfig.ts';
        $subject = str($config['domain'])->snake('-');

        if (! File::exists($abilityFile)) {
            throw new \Exception('O arquivo abilityConfig.ts não foi encontrado.');
        }

        $fileContent = File::get($abilityFile);

        $arrayStart = strpos($fileContent, 'const userSubjects = [');
        if ($arrayStart === false) {
            throw new \Exception('Array userSubjects não encontrado em abilityConfig.ts.');
        }

        $arrayEnd = strpos($fileContent, ']', $arrayStart);
        if ($arrayEnd === false) {
            throw new \Exception('Final do array userSubjects não encontrado.');
        }

        // Verifica se o subject já existe
        if (strpos($fileContent, "'{$subject}'", $arrayStart) === false) {
            // Insere o novo subject antes do fechamento do array
            $updatedContent = substr_replace($fileContent, ", '{$subject}'", $arrayEnd, 0);
            File::put($abilityFile, $updatedContent);
        }

        return true;
    }
}
