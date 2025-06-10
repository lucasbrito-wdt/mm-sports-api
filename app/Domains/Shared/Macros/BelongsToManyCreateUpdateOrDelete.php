<?php

namespace App\Domains\Shared\Macros;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BelongsToManyCreateUpdateOrDelete
{
    protected BelongsToMany $query;
    protected Collection $records;
    protected array $pivotAttributes;

    public function __construct(BelongsToMany $query, iterable $records, array $pivotAttributes = [])
    {
        $relatedKeyName = $query->getRelated()->getKeyName();
        $allowedRecordIds = $query->pluck($relatedKeyName);

        $this->query = $query;
        $this->pivotAttributes = $pivotAttributes;
        $this->records = collect($records)->filter(
            function ($record) use ($relatedKeyName, $allowedRecordIds) {
                $id = $record[$relatedKeyName] ?? null;

                return $id === null || $allowedRecordIds->contains($id);
            },
        );
    }

    public function __invoke()
    {
        return DB::transaction(function () {
            $this->deleteMissingRecords();

            return $this->updateOrCreateRecords();
        });
    }

    protected function deleteMissingRecords(): void
    {
        $recordKeyName = $this->query->getRelated()->getKeyName();

        $existingRecordIds = $this->records
            ->pluck($recordKeyName)
            ->filter();

        if ($existingRecordIds->isEmpty()) {
            $this->query->detach();
            return;
        }

        $this->query->whereNotIn($recordKeyName, $existingRecordIds)->detach();
    }

    protected function updateOrCreateRecords(): array
    {
        $recordKeyName = $this->query->getRelated()->getKeyName();
        $parentKey = $this->query->getParentKeyName();
        $parentId = $this->query->getParent()->getKey();
        $results = [];

        foreach ($this->records as $record) {
            // Verifica se $record é um array antes de processá-lo
            if (!is_array($record)) {
                continue; // Pula este item se não for um array
            }

            $id = $record[$recordKeyName] ?? null;
            $pivotData = $this->extractPivotData($record);

            if ($id) {
                // Record já existe, atualiza
                $relatedModel = $this->query->getRelated()->find($id);

                if ($relatedModel) {
                    $relatedModel->fill($this->extractModelData($record))->save();
                    $this->query->updateExistingPivot($id, $pivotData);
                    $results[] = $relatedModel;
                }
            } else {
                // Cria novo registro
                $modelData = $this->extractModelData($record);
                $newModel = $this->query->getRelated()->create($modelData);
                $this->query->attach($newModel->getKey(), $pivotData);
                $results[] = $newModel;
            }
        }

        return $results;
    }

    protected function extractPivotData($record): array
    {
        // Verificar se $record é um array
        if (!is_array($record)) {
            return []; // Retornar array vazio se não for um array
        }

        $pivotData = [];

        // Adiciona dados específicos do pivot
        foreach ($this->query->getPivotColumns() as $pivotColumn) {
            if (isset($record[$pivotColumn])) {
                $pivotData[$pivotColumn] = $record[$pivotColumn];
            }
        }

        // Adiciona quaisquer atributos pivot adicionais
        foreach ($this->pivotAttributes as $key => $value) {
            $pivotData[$key] = $value;
        }

        return $pivotData;
    }

    protected function extractModelData(array $record): array
    {
        $pivotColumns = $this->query->getPivotColumns();
        return collect($record)
            ->except($pivotColumns)
            ->toArray();
    }
}
