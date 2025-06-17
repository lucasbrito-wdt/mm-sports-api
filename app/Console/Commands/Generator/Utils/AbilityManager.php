<?php

namespace App\Console\Commands\Generator\Utils;

use App\Domains\ACL\Enums\PermissionActionsEnum;
use App\Domains\ACL\Models\Permission;
use Illuminate\Support\Facades\File;

class AbilityManager
{
    /**
     * Cria as abilities para o domínio e atualiza o config/permission_list.php
     */
    public function createAbilityAndConfig(string $domainName): void
    {
        $configPath = config_path('permission_list.php');

        // Verificar se o arquivo existe
        if (! File::exists($configPath)) {
            throw new \Exception("Arquivo de configuração não encontrado: {$configPath}");
        }

        // Ler a configuração atual
        $config = require $configPath;
        if (! is_array($config)) {
            $config = [];
        }

        $key = strtolower($domainName);

        // Inicializar o array para o domínio se não existir
        if (! isset($config[$key])) {
            $config[$key] = [];
        }

        $permissionsAdded = [];

        foreach (collect(PermissionActionsEnum::cases())->except(['block', 'manage'])->toArray() as $action) {
            $slug = $key.' '.$action->value;

            // Cria a permission no banco se não existir
            if (! Permission::where('slug', $slug)->exists()) {
                Permission::create([
                    'name' => $domainName,
                    'slug' => $slug,
                ]);
            }

            // Adiciona no config se não existir
            if (! in_array($slug, $config[$key])) {
                $config[$key][] = $slug;
                $permissionsAdded[] = $slug;
            }
        }

        // Só escreve o arquivo se houve mudanças
        if (! empty($permissionsAdded)) {
            $this->writeConfigFile($configPath, $config);
            echo "✓ Abilities adicionadas para {$domainName}: ".implode(', ', $permissionsAdded)."\n";
        } else {
            echo "ℹ Todas as abilities para {$domainName} já existem no arquivo de configuração.\n";
        }
    }

    /**
     * Escreve o arquivo de configuração mantendo o formato original
     */
    private function writeConfigFile(string $configPath, array $config): void
    {
        $content = "<?php\n\nreturn [\n";

        foreach ($config as $key => $permissions) {
            $content .= "    '{$key}' => [\n";
            foreach ($permissions as $permission) {
                $content .= "        '{$permission}',\n";
            }
            $content .= "    ],\n";
        }

        $content .= "];\n";

        File::put($configPath, $content);
    }

    /**
     * Adiciona abilities para um modelo específico
     */
    public function addAbility(string $domainName, string $modelName): void
    {
        $configPath = config_path('permission_list.php');

        // Verificar se o arquivo existe
        if (! File::exists($configPath)) {
            throw new \Exception("Arquivo de configuração não encontrado: {$configPath}");
        }

        // Ler a configuração atual
        $config = require $configPath;
        if (! is_array($config)) {
            $config = [];
        }

        $key = strtolower($modelName);

        // Inicializar o array para o modelo se não existir
        if (! isset($config[$key])) {
            $config[$key] = [];
        }

        $permissionsAdded = [];

        // Gerar as permissões baseadas no enum
        foreach (collect(PermissionActionsEnum::cases())->except(['block', 'manage'])->toArray() as $action) {
            $slug = $key.' '.$action->value;

            // Cria a permission no banco se não existir
            if (! Permission::where('slug', $slug)->exists()) {
                Permission::create([
                    'name' => $modelName,
                    'slug' => $slug,
                ]);
            }

            // Adiciona no config se não existir
            if (! in_array($slug, $config[$key])) {
                $config[$key][] = $slug;
                $permissionsAdded[] = $slug;
            }
        }

        // Só escreve o arquivo se houve mudanças
        if (! empty($permissionsAdded)) {
            $this->writeConfigFile($configPath, $config);
            echo "✓ Abilities adicionadas para {$modelName}: ".implode(', ', $permissionsAdded)."\n";
        } else {
            echo "ℹ Todas as abilities para {$modelName} já existem no arquivo de configuração.\n";
        }
    }
}
