<?php

namespace App\Console\Commands\Generator\Generators\BackEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MigrationGenerator
{
    private TemplateManager $templateManager;

    public function __construct(TemplateManager $templateManager)
    {
        $this->templateManager = $templateManager;
    }

    public function generate(array $config): bool
    {
        $domain = $config['domain'];
        $modelName = $config['model'];
        $tableName = Str::snake(Str::plural($modelName));

        // Construir campos para a migration
        $fields = $this->buildMigrationFields($config);

        // Gerar conteúdo da migration
        $migrationContent = $this->templateManager->processStub(
            'BackEnd/migration.stub',
            [
                '{{className}}' => 'Create' . Str::studly($tableName) . 'Table',
                '{{tableName}}' => $tableName,
                '{{fields}}' => $fields,
            ]
        );

        // Criar diretório se não existir
        $migrationsDir = app_path("Domains/{$domain}/Migrations");
        if (!File::exists($migrationsDir)) {
            File::makeDirectory($migrationsDir, 0755, true);
        }

        // Nome do arquivo com timestamp
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_{$tableName}_table.php";

        // Salvar o arquivo
        $migrationPath = "{$migrationsDir}/{$filename}";
        File::put($migrationPath, $migrationContent);

        return true;
    }

    private function buildMigrationFields(array $config): string
    {
        $fields = [];

        // Adicionar ID e timestamps
        $fields[] = "\$table->ulid('id')->primary();";

        // Processar campos do schema
        $columns = explode(';', rtrim($config['schema'], ';'));
        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column);
            @[$type, $option1, $option2, $required] = explode(',', $params ?? '');

            if (!$field || !$type) {
                continue;
            }

            if ($option1 === 'req') {
                $required = true;
                $option1 = null;
            }

            if ($option2 === 'req') {
                $required = true;
                $option2 = null;
            }

            $migrationField = $this->buildMigrationField($field, $type, $option1, $option2, $required);
            if ($migrationField) {
                $fields[] = $migrationField;
            }
        }

        // Processar chaves estrangeiras
        if (!empty($config['foreignKeys'])) {
            foreach ($config['foreignKeys'] as $fk) {
                if (!isset($fk['model']) || !isset($fk['relation'])) {
                    continue;
                }

                if (!isset($fk['required'])) {
                    $fk['required'] = true; // Default to required if not specified
                }

                if ($fk['relation'] === 'belongsTo' || $fk['relation'] === 'hasMany' || $fk['relation'] === 'hasOne') {
                    $foreignKey = Str::snake($fk['model']) . '_id';
                    $line = "\$table->foreignUlid('{$foreignKey}')";

                    // Só adiciona nullable se NÃO for obrigatório
                    if (!$fk['required']) {
                        $line .= "->nullable()";
                    }

                    $line .= "->constrained('" . Str::snake(Str::plural($fk['model'])) . "')";

                    // Define comportamento de delete baseado na obrigatoriedade
                    if ($fk['required']) {
                        $line .= "->onDelete('cascade');";
                    } else {
                        $line .= "->nullOnDelete();";
                    }

                    $fields[] = $line;
                }
            }
        }

        // Adicionar timestamps
        $fields[] = "\$table->timestamps();";

        return implode("\n            ", $fields);
    }

    private function buildMigrationField(string $field, string $type, ?string $option1, ?string $option2, ?bool $required): ?string
    {
        switch (strtolower($type)) {
            case 'string':
                $length = $option1 ?: 255;
                $result = "\$table->string('{$field}', {$length})";
                break;

            case 'integer':
            case 'biginteger':
                $result = "\$table->integer('{$field}')";
                break;

            case 'boolean':
                $result = "\$table->boolean('{$field}')";
                break;

            case 'text':
                $result = "\$table->text('{$field}')";
                break;

            case 'date':
                $result = "\$table->date('{$field}')";
                break;

            case 'datetime':
                $result = "\$table->dateTime('{$field}')->default(now())";
                break;

            case 'decimal':
                $precision = $option1 ?: 8;
                $scale = $option2 ?: 2;
                $result = "\$table->decimal('{$field}', {$precision}, {$scale})";
                break;

            case 'float':
                $result = "\$table->float('{$field}')";
                break;

            case 'json':
                $result = "\$table->json('{$field}')";
                break;

            case 'uuid':
                $result = "\$table->uuid('{$field}')";
                break;

            case 'enum':
                $options = array_map(function ($option) {
                    return "'{$option}'";
                }, explode('|', $option1));
                $optionsStr = implode(', ', $options);
                $result = "\$table->enum('{$field}', [{$optionsStr}])";
                break;

            default:
                return null;
        }

        // Só adiciona ->nullable() se o campo NÃO for obrigatório
        if (!$required) {
            $result .= "->nullable()";
        }

        return $result . ';';
    }
}
