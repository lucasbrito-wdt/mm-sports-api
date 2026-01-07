<?php

namespace App\Console\Commands\Generator\Generators\FrontEnd;

use Illuminate\Support\Str;

class ZodSchemaGenerator
{
    /**
     * Gera schema Zod para criação (create)
     */
    public function generateCreateSchema(array $config): string
    {
        $schema = $config['schema'] ?? '';
        $foreignKeys = $config['foreignKeys'] ?? [];

        $fields = $this->parseSchemaToZodFields($schema, $foreignKeys, false);

        return $this->buildSchemaObject('Create', $fields);
    }

    /**
     * Gera schema Zod para atualização (update)
     */
    public function generateUpdateSchema(array $config): string
    {
        $schema = $config['schema'] ?? '';
        $foreignKeys = $config['foreignKeys'] ?? [];

        // Em update, alguns campos podem ser opcionais
        $fields = $this->parseSchemaToZodFields($schema, $foreignKeys, true);

        return $this->buildSchemaObject('Update', $fields);
    }

    /**
     * Constrói o objeto schema Zod
     */
    private function buildSchemaObject(string $suffix, array $fields): string
    {
        if (empty($fields)) {
            return "z.object({})";
        }

        $indentedFields = array_map(fn($field) => "  {$field}", $fields);
        return "z.object({\n" . implode(",\n", $indentedFields) . "\n})";
    }

    /**
     * Converte schema string em campos Zod
     */
    private function parseSchemaToZodFields(string $schema, array $foreignKeys, bool $isUpdate = false): array
    {
        $fields = [];
        $columns = explode(';', rtrim($schema, ';'));

        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column, 2);

            if (!$field || !$params) {
                continue;
            }

            // Pular campos que não devem aparecer no formulário
            if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $zodField = $this->buildZodField($field, $params, $isUpdate);
            if ($zodField) {
                $fields[] = $zodField;
            }
        }

        // Adicionar campos de foreign keys
        foreach ($foreignKeys as $fk) {
            if (($fk['relation'] ?? '') === 'belongsTo') {
                $foreignKey = Str::snake($fk['model']) . '_id';
                $required = $fk['required'] ?? false;
                
                // Em update, FK geralmente é opcional
                $isOptional = $isUpdate || !$required;
                
                $zodField = $this->buildZodFieldForFK($foreignKey, $isOptional);
                $fields[] = $zodField;
            }
        }

        return $fields;
    }

    /**
     * Constrói um campo Zod baseado na configuração
     */
    private function buildZodField(string $fieldName, string $params, bool $isUpdate = false): ?string
    {
        $this->currentFieldName = $fieldName;
        
        $paramParts = explode(',', $params);
        $type = trim($paramParts[0] ?? 'string');

        $required = false;
        $option1 = null;
        $option2 = null;
        $enumValues = [];

        // Processar parâmetros
        for ($i = 1; $i < count($paramParts); $i++) {
            $part = trim($paramParts[$i]);

            if (strtolower($part) === 'req') {
                $required = true;
                continue;
            }

            if (str_contains($part, '|')) {
                $enumValues = array_map('trim', explode('|', $part));
                continue;
            }

            if (!$option1) {
                $option1 = $part;
            } elseif (!$option2) {
                $option2 = $part;
            }
        }

        // Construir schema Zod baseado no tipo
        $zodSchema = $this->getZodTypeForFieldType($type, $option1, $enumValues);

        // Adicionar validações
        $zodSchema = $this->addZodValidations($zodSchema, $type, $option1, $option2, $required);

        // Em update, alguns campos podem ser opcionais mesmo se marcados como required
        $isOptional = $isUpdate ? false : !$required;
        if ($isOptional) {
            $zodSchema .= '.optional()';
        }

        return "{$fieldName}: {$zodSchema}";
    }

    /**
     * Obtém o tipo Zod baseado no tipo do campo
     */
    private function getZodTypeForFieldType(string $type, ?string $option1, array $enumValues): string
    {
        return match (strtolower($type)) {
            'string', 'text' => 'z.string()',
            'integer', 'biginteger' => 'z.number().int()',
            'decimal', 'float', 'double' => 'z.number()',
            'boolean' => 'z.boolean()',
            'date', 'datetime', 'timestamp' => 'z.date()',
            'enum' => $this->buildEnumSchema($enumValues, $option1),
            'email' => 'z.string().email()',
            'json' => 'z.record(z.string(), z.any())',
            default => 'z.string()',
        };
    }

    /**
     * Constrói schema enum
     */
    private function buildEnumSchema(array $enumValues, ?string $option1): string
    {
        if (!empty($enumValues)) {
            $values = array_map(fn($v) => "'{$v}'", $enumValues);
            return 'z.enum([' . implode(', ', $values) . '])';
        }

        if ($option1 && str_contains($option1, '|')) {
            $values = array_map('trim', explode('|', $option1));
            $values = array_map(fn($v) => "'{$v}'", $values);
            return 'z.enum([' . implode(', ', $values) . '])';
        }

        return 'z.string()';
    }

    /**
     * Adiciona validações ao schema Zod
     */
    private function addZodValidations(string $zodSchema, string $type, ?string $option1, ?string $option2, bool $required): string
    {
        // Validação de obrigatório com mensagem customizada
        if ($required && in_array(strtolower($type), ['string', 'text'])) {
            $fieldLabel = ucfirst(str_replace('_', ' ', $this->getCurrentFieldName()));
            if (str_contains($zodSchema, 'z.string()')) {
                $zodSchema = str_replace('z.string()', "z.string({\n    required_error: '{$fieldLabel} é obrigatório',\n  })", $zodSchema);
            }
            $zodSchema .= ".min(1, 'Campo obrigatório')";
        }

        // Validação de máximo para strings
        if (in_array(strtolower($type), ['string', 'text']) && $option1 && is_numeric($option1)) {
            $max = intval($option1);
            $zodSchema .= ".max({$max}, 'Máximo {$max} caracteres')";
        }

        // Validação de mínimo para strings
        if (in_array(strtolower($type), ['string', 'text']) && $option2 && is_numeric($option2)) {
            $min = intval($option2);
            $zodSchema .= ".min({$min}, 'Mínimo {$min} caracteres')";
        }

        // Validação de email
        if (strtolower($type) === 'email') {
            $zodSchema .= ".email('E-mail inválido')";
        }

        // Validação de número positivo
        if (in_array(strtolower($type), ['integer', 'biginteger', 'decimal', 'float', 'double'])) {
            if ($option1 && is_numeric($option1)) {
                $min = intval($option1);
                $zodSchema .= ".min({$min}, 'Valor mínimo: {$min}')";
            }
            if ($option2 && is_numeric($option2)) {
                $max = intval($option2);
                $zodSchema .= ".max({$max}, 'Valor máximo: {$max}')";
            }
        }

        return $zodSchema;
    }

    /**
     * Armazena o nome do campo atual para uso em mensagens de erro
     */
    private ?string $currentFieldName = null;

    /**
     * Obtém o nome do campo atual
     */
    private function getCurrentFieldName(): string
    {
        return $this->currentFieldName ?? 'campo';
    }

    /**
     * Constrói campo Zod para foreign key
     */
    private function buildZodFieldForFK(string $fieldName, bool $isOptional): string
    {
        $schema = "z.string().uuid('ID inválido')";
        
        if ($isOptional) {
            $schema .= '.optional()';
        }

        return "{$fieldName}: {$schema}";
    }
}
