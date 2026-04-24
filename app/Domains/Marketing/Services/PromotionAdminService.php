<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Models\Promotion;
use App\Domains\Marketing\Models\PromotionItem;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Tracking\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class PromotionAdminService extends BaseService
{
    public function __construct(
        private readonly Promotion $promotion,
        private readonly PromotionItem $promotionItem,
        private readonly AuditLogger $auditLogger,
    ) {
        $this->setModel($this->promotion);
    }

    public function index(array $options = [], ?\Closure $builderCallback = null)
    {
        $enrich = function ($query) {
            $query->with('items');
        };
        if ($builderCallback) {
            $enrich = function ($query) use ($enrich, $builderCallback) {
                $enrich($query);
                $builderCallback($query);
            };
        }
        if (empty($options['sort_by'])) {
            $options['sort_by'] = 'starts_at';
            $options['sort_order'] = 'desc';
        }

        return parent::index($options, $enrich);
    }

    public function show(string $id)
    {
        return $this->findById($id)->load('items');
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);
            if (! array_key_exists('is_active', $data)) {
                $data['is_active'] = true;
            }
            $data = $this->onlyPromotionFillable($data);
            /** @var Promotion $model */
            $model = $this->getModel()->newQuery()->create($data);
            $this->syncItems($model, is_array($items) ? $items : []);
            $model->load('items');
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'promotions.create',
                $model,
                null,
                $this->promotionAuditPayload($model),
                request()
            );

            return $model;
        });
    }

    public function update(array $data, string $id)
    {
        return DB::transaction(function () use ($data, $id) {
            /** @var Promotion $record */
            $record = $this->findById($id);
            $old = $this->promotionAuditPayload($record);
            $items = $data['items'] ?? null;
            unset($data['items']);
            $data = $this->onlyPromotionFillable($data);
            if ($data !== []) {
                $record->update($data);
            }
            if (is_array($items)) {
                $this->syncItems($record, $items);
            }
            $record->refresh();
            $record->load('items');
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'promotions.update',
                $record,
                $old,
                $this->promotionAuditPayload($record),
                request()
            );

            return $record;
        });
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $record = $this->findById($id);
            $old = $this->promotionAuditPayload($record);
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'promotions.delete',
                $record,
                $old,
                null,
                request()
            );

            return $record->delete();
        });
    }

    private function onlyPromotionFillable(array $data): array
    {
        $keys = $this->getModel()->getFillable();

        return array_intersect_key($data, array_flip($keys));
    }

    /**
     * @param  list<array{product_id?: string|null, product_variant_id?: string|null}>  $rows
     */
    private function syncItems(Promotion $promotion, array $rows): void
    {
        $promotion->items()->delete();
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pid = $row['product_id'] ?? null;
            $vid = $row['product_variant_id'] ?? null;
            if (empty($pid) && empty($vid)) {
                continue;
            }
            $this->promotionItem->newQuery()->create([
                'promotion_id' => $promotion->id,
                'product_id' => $pid,
                'product_variant_id' => $vid,
            ]);
        }
    }

    private function promotionAuditPayload(Promotion $model): array
    {
        $a = $model->getAttributes();
        if (isset($a['type']) && $a['type'] instanceof \UnitEnum) {
            $a['type'] = $a['type'] instanceof \BackedEnum ? $a['type']->value : (string) $a['type'];
        }
        if (! $model->relationLoaded('items')) {
            $model->load('items');
        }
        $a['items'] = $model->items->map(fn (PromotionItem $i) => [
            'product_id' => $i->product_id ? (string) $i->product_id : null,
            'product_variant_id' => $i->product_variant_id ? (string) $i->product_variant_id : null,
        ])->all();

        return $a;
    }
}
