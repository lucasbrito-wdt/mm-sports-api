<?php

namespace App\Console\Commands\Generator\Generators\FrontEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\error;

class TypesGenerator
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
            '%s/%s/%s',
            $frontEndAbsoluteDir,
            'pages',
            Str::snake($domain, '-')
        );

        // Criar diretório se não existir
        if (! File::exists($fullPath)) {
            File::makeDirectory($fullPath, 0755, true);
        }

        // Nome do arquivo seguindo padrão antigo
        $fileName = 'types.ts';
        $filePath = $fullPath.'/'.$fileName;

        // Verificar se o arquivo já existe
        if (File::exists($filePath) && ! ($config['force'] ?? false)) {
            return false;
        }

        // Construir campos da interface
        $attributes = $this->buildTypeAttributes($schema, $config['foreignKeys'] ?? []);

        // Usar stub
        $typesContent = $this->templateManager->processStub(
            'FrontEnd/types.stub',
            [
                '{{interface_name}}' => 'I'.$modelName,
                '{{attributes}}' => $attributes,
                '{{imports}}' => implode("\n", $this->imports),
            ]
        );

        // Salvar o arquivo
        File::put($filePath, $typesContent);

        return true;
    }

    private function buildTypeAttributes(string $schema, array $foreignKeys): string
    {
        $attributes = [];
        $columns = explode(';', rtrim($schema, ';'));

        // Adicionar ID primeiro
        $attributes[] = '    id: string';

        // Processar campos do schema
        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column);
            @[$type, $option, $required] = explode(',', $params ?? '');

            if (! $field) {
                continue;
            }

            $tsType = $this->mapTypeToTS($type);
            $isOptional = ($required !== 'req') ? '?' : '';

            $attributes[] = "    {$field}{$isOptional}: {$tsType}";
        }

        // Adicionar campos para chaves estrangeiras
        if (! empty($foreignKeys)) {
            foreach ($foreignKeys as $fk) {
                if (! isset($fk['model']) || ! isset($fk['relation'])) {
                    error('TypesGenerator: Chave estrangeira inválida: modelo ou relação ausente.');

                    continue; // Ignorar se não tiver modelo ou relação
                }

                $relatedModelName = $fk['model'] ?? Str::studly(Str::singular($fk['foreignTable']));

                $this->imports[] = "import type { I{$relatedModelName} } from '@/pages/".Str::lower($fk['model'])."/types'";

                if ($fk['relation'] === 'belongsTo') {
                    $foreignKey = Str::snake($fk['model']).'_id';
                    $isOptional = ! ($fk['required'] ?? false) ? '?' : '';
                    $attributes[] = "    {$foreignKey}{$isOptional}: string";

                    // Adicionar o objeto relacionado
                    $relationName = lcfirst($fk['model']);
                    $attributes[] = "    {$relationName}?: I{$fk['model']}";
                } elseif ($fk['relation'] === 'hasMany') {
                    $relationName = Str::camel(Str::plural($fk['model']));
                    $attributes[] = "    {$relationName}?: I{$fk['model']}[]";
                } elseif ($fk['relation'] === 'belongsToMany') {
                    $relationName = Str::camel(Str::plural($fk['model']));
                    $attributes[] = "    {$relationName}?: I{$fk['model']}[]";
                }
            }
        }

        // Adicionar timestamps
        $attributes[] = '    created_at?: string';
        $attributes[] = '    updated_at?: string';

        return implode("\n", $attributes);
    }

    private function mapTypeToTS(string $type): string
    {
        return match (strtolower($type)) {
            'integer', 'biginteger', 'decimal', 'float' => 'number',
            'boolean' => 'boolean',
            'date', 'datetime', 'text', 'string', 'enum', 'uuid' => 'string',
            'json' => 'Record<string, any>',
            default => 'any'
        };
    }
}
