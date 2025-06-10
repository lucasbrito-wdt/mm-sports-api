<?php

namespace App\Console\Commands\Generator\Validators;

class SchemaValidator
{
    public function validate(string $schema): bool|string
    {
        if (empty($schema)) {
            return 'O schema não pode estar vazio';
        }

        $columns = explode(';', rtrim($schema, ';'));
        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column);
            @[$type, $option, $required] = explode(',', $params ?? '');

            if (!$field || !$type) {
                return 'Schema inválido. Use o formato: campo=tipo,opção,req';
            }

            if ($required && strtolower($required) !== 'req') {
                return 'O parâmetro de obrigatoriedade deve ser "req"';
            }

            if (!$this->isValidType($type)) {
                return "O tipo '$type' não é válido";
            }
        }

        return true;
    }

    private function isValidType(string $type): bool
    {
        $validTypes = [
            'string',
            'integer',
            'bigInteger',
            'boolean',
            'date',
            'dateTime',
            'decimal',
            'float',
            'text',
            'json',
            'enum',
            'uuid'
        ];

        return in_array(strtolower($type), $validTypes);
    }
}
