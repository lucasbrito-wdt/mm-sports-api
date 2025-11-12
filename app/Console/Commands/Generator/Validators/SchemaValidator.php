<?php
/*
 * @Autor: Lucas Brito
 * @Data: 2025-11-07 20:44:02
 * @Último Editor: Lucas Brito
 * @Última Hora da Edição: 2025-11-12 15:22:32
 * @Caminho do Arquivo: \cdf_generator\app\Console\Commands\Generator\Validators\SchemaValidator.php
 * @Descrição:
 */

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
            @[$field, $params] = explode('=', $column, 2);

            if (!$field || !$params) {
                return 'Schema inválido. Use o formato: campo=tipo,opção,req';
            }

            $paramParts = explode(',', $params);
            $type = $paramParts[0] ?? '';

            if (!$type) {
                return 'Schema inválido. Use o formato: campo=tipo,opção,req';
            }

            if (!$this->isValidType($type)) {
                return "O tipo '$type' não é válido";
            }

            // Validar enum com valores
            if (strtolower($type) === 'enum') {
                $hasEnumValues = false;
                foreach ($paramParts as $part) {
                    if (str_contains($part, '|')) {
                        $hasEnumValues = true;
                        $enumValues = explode('|', $part);
                        if (count($enumValues) < 2) {
                            return "Enum '{$field}' deve ter pelo menos 2 valores separados por '|'";
                        }
                        foreach ($enumValues as $value) {
                            if (empty(trim($value))) {
                                return "Enum '{$field}' contém valor vazio";
                            }
                        }
                        break;
                    }
                }
                if (!$hasEnumValues) {
                    return "Enum '{$field}' deve especificar valores no formato: tipo=enum,req,VALOR1|VALOR2";
                }
            }

            // Validar parâmetro de obrigatoriedade
            foreach ($paramParts as $part) {
                $trimmedPart = trim($part);
                if ($trimmedPart && !str_contains($trimmedPart, '|') && strtolower($trimmedPart) !== 'req' && strtolower($trimmedPart) !== strtolower($type)) {
                    // Se não é 'req', não é o tipo e não contém '|', pode ser uma opção válida
                    // Permitir opções como 'unique', 'nullable', etc.
                    continue;
                }
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
            'datetime',
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
