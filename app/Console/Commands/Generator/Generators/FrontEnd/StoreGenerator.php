<?php

namespace App\Console\Commands\Generator\Generators\FrontEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class StoreGenerator
{
    use FrontendPathTrait;

    private TemplateManager $templateManager;

    private array $imports = [];

    public function __construct(TemplateManager $templateManager)
    {
        $this->templateManager = $templateManager;
    }

    public function generate(array $config): bool
    {
        $modelName = $config['model'];
        $domain = $config['domain'];
        $schema = $config['schema'];

        $frontEndAbsoluteDir = $this->getFrontendPath();

        $fullPath = sprintf(
            '%s/%s/%s/%s',
            $frontEndAbsoluteDir,
            'pages',
            Str::snake($domain, '-'),
            'stores'
        );

        // Criar diretório se não existir
        if (! File::exists($fullPath)) {
            File::makeDirectory($fullPath, 0755, true);
        }

        // Nome do arquivo seguindo padrão antigo
        $storeName = 'use'.$modelName.'Store';
        $fileName = $storeName.'.ts';
        $filePath = $fullPath.'/'.$fileName;

        // Verificar se o arquivo já existe
        if (File::exists($filePath) && ! ($config['force'] ?? false)) {
            return false;
        }

        // Construir variáveis para o template
        $templateVars = $this->buildTemplateVariables($config, $storeName);

        // Usar stub antiga
        $storeContent = $this->templateManager->processStub(
            'FrontEnd/store.stub',
            $templateVars
        );

        // Salvar o arquivo
        File::put($filePath, $storeContent);

        return true;
    }

    private function buildTemplateVariables(array $config, string $storeName): array
    {
        $modelName = $config['model'];
        $domain = $config['domain'];
        $schema = $config['schema'];
        $foreignKeys = $config['foreignKeys'] ?? [];

        // Construir atributos para defaultValue
        $attributes = $this->buildDefaultAttributes($schema, $foreignKeys);

        // Construir nome do serviço
        $serviceName = $modelName.'Service';

        // Construir chave de ordenação padrão (primeiro campo do schema)
        $orderKeyDefault = $this->getDefaultOrderKey($schema);

        // Construir FK states, loadings, fetchs
        $fkStates = $this->buildForeignKeyStates($foreignKeys);
        $fkLoadings = $this->buildForeignKeyLoadings($foreignKeys);
        $fkFetchs = $this->buildForeignKeyFetchs($config, $foreignKeys);

        return [
            '{{store_name}}' => $storeName,
            '{{service_name}}' => $serviceName,
            '{{interface_name}}' => 'I'.$modelName,
            '{{crud_name}}' => $modelName,
            '{{entity_singular_var}}' => strtolower(Str::singular($domain)),
            '{{attributes}}' => $attributes,
            '{{orderKeyDefault}}' => $orderKeyDefault,
            '{{fk_fetchs}}' => implode("\n", $fkFetchs),
            '{{fk_states}}' => implode("\n", $fkStates),
            '{{fk_loadings}}' => implode("\n", $fkLoadings),
            '{{imports}}' => implode("\n", $this->imports),
        ];
    }

    private function buildDefaultAttributes(string $schema, array $foreignKeys): string
    {
        $attributes = [];
        $columns = explode(';', rtrim($schema, ';'));

        // Processar campos do schema
        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column);
            @[$type, $option, $required] = explode(',', $params ?? '');

            if (! $field) {
                continue;
            }

            $defaultValue = $this->getDefaultValueForType($type);
            $attributes[] = "    {$field}: {$defaultValue},";
        }

        // Adicionar campos FK
        foreach ($foreignKeys as $fk) {
            $relation = $fk['relation'] ?? 'belongsTo';

            if ($relation === 'belongsTo') {
                // Inferir informações se não estiverem definidas
                $foreignTable = $fk['foreignTable'] ?? '';
                $localKey = $fk['localKey'] ?? '';
                $relatedModelName = $fk['model'] ?? Str::studly(Str::singular($foreignTable));
                $foreignKey = $localKey ?: (Str::snake($relatedModelName).'_id');

                $attributes[] = "    {$foreignKey}: null,";
            }
        }

        return implode("\n", $attributes);
    }

    private function getDefaultValueForType(string $type): string
    {
        return match (strtolower($type)) {
            'integer', 'biginteger', 'decimal', 'float' => 'null',
            'boolean' => 'false',
            'string', 'text', 'enum', 'uuid' => "''",
            'date', 'datetime' => 'null',
            'json' => '{}',
            default => 'null'
        };
    }

    private function getDefaultOrderKey(string $schema): string
    {
        $columns = explode(';', rtrim($schema, ';'));
        if (! empty($columns)) {
            @[$field] = explode('=', $columns[0]);

            return $field ?: 'id';
        }

        return 'id';
    }

    private function buildForeignKeyStates(array $foreignKeys): array
    {
        $states = [];
        foreach ($foreignKeys as $fk) {
            // Inferir informações se não estiverem definidas
            $foreignTable = $fk['foreignTable'] ?? '';
            $relatedModelName = $fk['model'] ?? Str::studly(Str::singular($foreignTable));

            if ($relatedModelName) {
                $pluralName = Str::plural(strtolower($relatedModelName));
                $states[] = "    {$pluralName}: [] as I{$relatedModelName}[],";
            }
        }

        return $states;
    }

    private function buildForeignKeyLoadings(array $foreignKeys): array
    {
        if (empty($foreignKeys)) {
            return [];
        }

        $loadings = [];
        $loadings[] = '      loading: {';
        $loadings[] = '        '.implode(",\n        ", array_map(fn ($fk) => Str::plural(strtolower($fk['model'] ?? '')).': false', $foreignKeys)).'';
        $loadings[] = '      },';

        return $loadings;
    }

    private function buildForeignKeyFetchs(array $config, array $foreignKeys): array
    {
        if (! empty($foreignKeys)) {
            $this->imports[] = "import { handleError } from '@codifytech/services/error-handling'";
        }

        $fetchs = [];
        foreach ($foreignKeys as $fk) {
            // Inferir informações se não estiverem definidas
            $foreignTable = $fk['foreignTable'] ?? '';
            $relatedModelName = $fk['model'] ?? Str::studly(Str::singular($foreignTable));
            $modelName = Str::studly(Str::singular($config['model']));

            $this->imports[] = "import type { I{$relatedModelName} } from '@/pages/".Str::lower($fk['model'])."/types'";

            if ($relatedModelName) {
                $pluralName = Str::plural(strtolower($relatedModelName));
                $methodName = 'fetch'.Str::plural($relatedModelName);
                $fetchs[] = "    async {$methodName}() {";
                $fetchs[] = "      this.loading.{$pluralName} = true";
                $fetchs[] = '      try {';
                $fetchs[] = "        const data = await {$modelName}Service.get<{$relatedModelName}[]>({}, 'listar/".Str::lower($relatedModelName)."')";
                $fetchs[] = "        this.{$pluralName} = data";
                $fetchs[] = '      } catch (error) {';
                $fetchs[] = '        handleError(error)';
                $fetchs[] = '      } finally {';
                $fetchs[] = "        this.loading.{$pluralName} = false";
                $fetchs[] = '      }';
                $fetchs[] = '    },';
            }
        }

        return $fetchs;
    }
}
