<?php

namespace App\Console\Commands\Generator\Generators\BackEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\warning;

class ModelGenerator
{
    private TemplateManager $templateManager;

    public function __construct(TemplateManager $templateManager)
    {
        $this->templateManager = $templateManager;
    }

    public function generate(array $config): bool
    {
        $modelName = $config['model'];
        $domain = $config['domain'];

        // Preparar dados para o modelo
        $tableName = Str::snake(Str::plural($modelName));

        // Determinar campos fillable
        $fillable = $this->extractFillableFields($config);

        // Preparar imports base
        $imports = [];

        // Determinar traits
        $traits = [];

        $casts = $this->buildCasts($config['schema']);
        // Não geramos mais relacionamentos aqui, o ModelRelationsManager fará isso
        $relationships = "";

        // Gerar conteúdo do modelo
        $modelContent = $this->templateManager->processStub(
            'BackEnd/model.stub',
            [
                '{{namespace}}' => "App\\Domains\\{$domain}\\Models",
                '{{modelName}}' => $modelName,
                '{{tableName}}' => $tableName,
                '{{fillable}}' => $fillable,
                '{{imports}}' => implode("\n", $imports),
                '{{traits}}' => implode("\n    ", $traits),
                '{{casts}}' => $casts,
                '{{relationships}}' => $relationships,
            ]
        );

        // Criar diretório se não existir
        $modelDir = app_path("Domains/{$domain}/Models");
        if (!File::exists($modelDir)) {
            File::makeDirectory($modelDir, 0755, true);
        }

        // Salvar o arquivo
        $modelPath = "{$modelDir}/{$modelName}.php";
        File::put($modelPath, $modelContent);

        return true;
    }

    private function extractFillableFields(array $config): string
    {
        $fillable = [];

        // Extrair campos do schema
        if (!empty($config['schema'])) {
            $columns = explode(';', rtrim($config['schema'], ';'));
            foreach ($columns as $column) {
                @[$field, $params] = explode('=', $column);
                if ($field && $field !== 'id' && !Str::endsWith($field, '_at')) {
                    $fillable[] = "'{$field}'";
                }
            }
        }

        // Adicionar campos de chaves estrangeiras para relacionamentos belongsTo
        if (isset($config['foreignKeys'])) {
            foreach ($config['foreignKeys'] as $fk) {
                // Verifica se o modelo já está no fillable
                if (!isset($fk['model'])) {
                    warning("Não foi possível criar a chave estrangeira para o modelo: {$config['model']}, pois a model não foi especificada.");
                    continue;
                }

                $foreignKeyField = Str::snake($fk['model']) . '_id';
                if (!in_array("'{$foreignKeyField}'", $fillable)) {
                    $fillable[] = "'{$foreignKeyField}'";
                }
            }
        }

        if (empty($fillable)) {
            return '[]';
        }

        return '[' . implode(', ', $fillable) . ']';
    }

    private function buildCasts(string $schema): string
    {
        $casts = [];
        $columns = explode(';', rtrim($schema, ';'));

        $typeMap = [
            'boolean' => 'boolean',
            'integer' => 'integer',
            'decimal' => 'decimal:2',
            'float' => 'float',
            'date' => 'date',
            'datetime' => 'datetime',
            'json' => 'array',
        ];

        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column);
            @[$type] = explode(',', $params ?? '');

            if ($field && isset($typeMap[$type])) {
                $casts[] = "'{$field}' => '{$typeMap[$type]}'";
            }
        }

        return empty($casts) ? '' :
            "\n    protected \$casts = [\n        " .
            implode(",\n        ", $casts) .
            "\n    ];\n";
    }
}
