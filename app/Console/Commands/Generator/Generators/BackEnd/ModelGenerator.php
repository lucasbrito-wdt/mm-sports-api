<?php

namespace App\Console\Commands\Generator\Generators\BackEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
        if (!empty($config['foreignKeys'])) {
            foreach ($config['foreignKeys'] as $fk) {
                if ($fk['relation'] === 'belongsTo') {
                    $foreignKeyField = Str::snake($fk['model']) . '_id';
                    if (!in_array("'{$foreignKeyField}'", $fillable)) {
                        $fillable[] = "'{$foreignKeyField}'";
                    }
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
            'dateTime' => 'datetime',
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

    /**
     * Método substituído pelo ModelRelationsManager
     *
     * @deprecated Use ModelRelationsManager para criar relações entre modelos
     * @param array $config
     * @return string
     */
    private function buildRelationships(array $config): string
    {
        // Método não mais utilizado, o ModelRelationsManager cuida disso agora
        return '';
    }

    /**
     * @deprecated Use ModelRelationsManager para criar relações entre modelos
     */
    private function buildBelongsToRelationship(string $methodName, array $fk): string
    {
        // Método não mais utilizado, o ModelRelationsManager cuida disso agora
        return "";
    }

    /**
     * @deprecated Use ModelRelationsManager para criar relações entre modelos
     */
    private function buildHasManyRelationship(string $methodName, array $fk): string
    {
        // Método não mais utilizado, o ModelRelationsManager cuida disso agora
        return "";
    }

    /**
     * @deprecated Use ModelRelationsManager para criar relações entre modelos
     */
    private function buildHasOneRelationship(string $methodName, array $fk): string
    {
        // Método não mais utilizado, o ModelRelationsManager cuida disso agora
        return "";
    }

    /**
     * @deprecated Use ModelRelationsManager para criar relações entre modelos
     */
    private function buildBelongsToManyRelationship(string $methodName, array $fk, string $currentModel): string
    {
        // Método não mais utilizado, o ModelRelationsManager cuida disso agora
        $pluralMethodName = Str::plural($methodName);
        $pivotTable = Str::snake(Str::singular($currentModel) . '_' . Str::singular($fk['model']));

        return "/**
     * Relação com {$fk['model']} (muitos para muitos)
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function {$pluralMethodName}()
    {
        return \$this->belongsToMany(\\App\\Domains\\{$fk['domain']}\\Models\\{$fk['model']}::class, '{$pivotTable}');
    }";
    }
}
