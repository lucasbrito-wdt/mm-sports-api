<?php

namespace App\Domains\Shared\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SortHelper
{
    public static function applySort(Builder $query, string $sortBy, string $direction = 'asc', array $joins = []): Builder
    {
        self::prefixSelectColumns($query);

        $parts = explode('.', $sortBy);

        $model = $query->getModel();
        $currentTable = $model->getTable();
        $column = array_pop($parts);

        foreach ($parts as $relation) {
            $relationName = Str::camel($relation);

            $relationObj = $model->$relationName();
            $relatedTable = $relationObj->getRelated()->getTable();

            if ($relationObj instanceof BelongsTo) {
                $foreignKey = $relationObj->getQualifiedForeignKeyName();
                $ownerKey = $relationObj->getQualifiedOwnerKeyName();

                if (! in_array($relatedTable, $joins)) {
                    $query->leftJoin(
                        $relatedTable,
                        $foreignKey,
                        '=',
                        $ownerKey
                    );
                    $joins[] = $relatedTable;
                }
            } elseif ($relationObj instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                $pivotTable = $relationObj->getTable();
                $parentKey = $model->getTable() . '.' . $model->getKeyName();
                $foreignPivotKey = $pivotTable . '.' . $relationObj->getForeignPivotKeyName();
                $relatedPivotKey = $pivotTable . '.' . $relationObj->getRelatedPivotKeyName();
                $relatedKey = $relatedTable . '.' . $relationObj->getRelatedKeyName();

                if (! in_array($pivotTable, $joins)) {
                    $query->leftJoin(
                        $pivotTable,
                        $parentKey,
                        '=',
                        $foreignPivotKey
                    );
                    $joins[] = $pivotTable;
                }

                if (! in_array($relatedTable, $joins)) {
                    $query->leftJoin(
                        $relatedTable,
                        $relatedPivotKey,
                        '=',
                        $relatedKey
                    );
                    $joins[] = $relatedTable;
                }
            } else {
                try {
                    $foreignKey = $relationObj->getQualifiedParentKeyName();
                    $ownerKey = $relationObj->getQualifiedForeignKeyName();

                    if (! in_array($relatedTable, $joins)) {
                        $query->leftJoin(
                            $relatedTable,
                            $foreignKey,
                            '=',
                            $ownerKey
                        );
                        $joins[] = $relatedTable;
                    }
                } catch (\Exception $e) {
                    // Caso a relação não seja suportada, ignoramos e continuamos
                    // Registramos o erro para debug, mas não interrompemos o fluxo
                    \Log::warning("Relação não suportada: {$relationName}. " . $e->getMessage());
                    continue;
                }
            }

            $model = $relationObj->getRelated();
            $currentTable = $relatedTable;
        }

        // Sempre prefixa a coluna com a tabela correta
        return $query->orderBy("$currentTable.$column", $direction);
    }

    private static function prefixSelectColumns(Builder $query): void
    {
        $model = $query->getModel();
        $table = $model->getTable();

        // Se não tiver select explícito, adiciona tabela.* para evitar ambiguidade
        if (empty($query->getQuery()->columns)) {
            $query->select("$table.*");
        }
    }
}
