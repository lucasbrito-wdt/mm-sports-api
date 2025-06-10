<?php

namespace App\Console\Commands\Generator\Utils;

class SummaryBuilder
{
    public function buildSummary(array $config): string
    {
        $summary = [];

        // Informações gerais
        $summary[] = "Domínio: {$config['domain']}";
        $summary[] = "Model: {$config['model']}";
        $summary[] = "";

        // Schema
        $summary[] = "Schema:";
        $columns = explode(';', rtrim($config['schema'], ';'));
        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column);
            @[$type, $option, $required] = explode(',', $params ?? '');

            $fieldDescription = "  - {$field} ({$type}";

            if ($option) {
                $fieldDescription .= ", {$option}";
            }

            if (isset($required) && strtolower($required) === 'req') {
                $fieldDescription .= ", obrigatório";
            }

            $fieldDescription .= ")";
            $summary[] = $fieldDescription;
        }

        // Relações
        if (!empty($config['foreignKeys'])) {
            $summary[] = "";
            $summary[] = "Relações:";

            foreach ($config['foreignKeys'] as $fk) {
                $localKey = $fk['localKey'] ?? 'N/A';
                $foreignTable = $fk['foreignTable'] ?? 'N/A';
                $displayField = $fk['displayField'] ?? 'N/A';

                $summary[] = "  - {$localKey} -> {$foreignTable} (exibe: {$displayField})";
            }
        }

        // Extras
        $summary[] = "";
        $summary[] = "Extras:";
        $summary[] = "  - Testes: " . (($config['generateTests'] ?? false) ? 'Sim' : 'Não');
        $summary[] = "  - Documentação: " . (($config['generateDocs'] ?? false) ? 'Sim' : 'Não');

        return implode("\n", $summary);
    }
}
