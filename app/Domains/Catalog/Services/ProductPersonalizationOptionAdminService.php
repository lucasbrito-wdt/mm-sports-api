<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductPersonalizationOption;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Tracking\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProductPersonalizationOptionAdminService extends BaseService
{
    public function __construct(
        private readonly Product $product,
        private readonly ProductPersonalizationOption $productPersonalizationOption,
        private readonly AuditLogger $auditLogger,
    ) {
        $this->setModel($this->productPersonalizationOption);
    }

    public function indexForProduct(string $productId, array $options = [], ?\Closure $builderCallback = null)
    {
        $this->assertProduct($productId);
        $options['filters'] = is_array($options['filters'] ?? null) ? $options['filters'] : [];
        $options['filters']['product_id'] = $productId;
        if (empty($options['sort_by'])) {
            $options['sort_by'] = 'sort_order';
            $options['sort_order'] = 'asc';
        }

        return parent::index($options, $builderCallback);
    }

    public function showForProduct(string $productId, string $optionId)
    {
        $this->assertProduct($productId);

        return $this->getModel()->newQuery()
            ->where('product_id', $productId)
            ->whereKey($optionId)
            ->firstOrFail();
    }

    public function storeForProduct(string $productId, array $data)
    {
        return DB::transaction(function () use ($productId, $data) {
            $this->assertProduct($productId);
            $data = $this->onlyFillable($data);
            $data['product_id'] = $productId;
            if (! array_key_exists('is_required', $data)) {
                $data['is_required'] = false;
            }
            if (! array_key_exists('sort_order', $data)) {
                $data['sort_order'] = 0;
            }
            if (! array_key_exists('additional_price', $data)) {
                $data['additional_price'] = 0;
            }
            /** @var ProductPersonalizationOption $model */
            $model = $this->getModel()->newQuery()->create($data);
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'product_personalization_options.create',
                $model,
                null,
                $this->auditSnapshot($model),
                request()
            );

            return $model;
        });
    }

    public function updateForProduct(string $productId, string $optionId, array $data)
    {
        return DB::transaction(function () use ($productId, $optionId, $data) {
            $this->assertProduct($productId);
            /** @var ProductPersonalizationOption $record */
            $record = $this->getModel()->newQuery()
                ->where('product_id', $productId)
                ->whereKey($optionId)
                ->firstOrFail();
            $old = $this->auditSnapshot($record);
            $data = $this->onlyFillable($data);
            if ($data === []) {
                return $record;
            }
            $record->update($data);
            $record->refresh();
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'product_personalization_options.update',
                $record,
                $old,
                $this->auditSnapshot($record),
                request()
            );

            return $record;
        });
    }

    public function destroyForProduct(string $productId, string $optionId)
    {
        return DB::transaction(function () use ($productId, $optionId) {
            $this->assertProduct($productId);
            $record = $this->getModel()->newQuery()
                ->where('product_id', $productId)
                ->whereKey($optionId)
                ->firstOrFail();
            $old = $this->auditSnapshot($record);
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'product_personalization_options.delete',
                $record,
                $old,
                null,
                request()
            );

            return $record->delete();
        });
    }

    private function assertProduct(string $productId): void
    {
        $this->product->newQuery()->whereKey($productId)->firstOrFail();
    }

    private function onlyFillable(array $data): array
    {
        $keys = $this->getModel()->getFillable();

        return array_intersect_key($data, array_flip($keys));
    }

    private function auditSnapshot(Model $model): array
    {
        $a = $model->getAttributes();
        if (isset($a['type']) && $a['type'] instanceof \UnitEnum) {
            $a['type'] = $a['type'] instanceof \BackedEnum ? $a['type']->value : (string) $a['type'];
        }
        if (isset($a['options_json']) && is_string($a['options_json'] ?? null)) {
            $a['options_json'] = $model->getAttribute('options_json');
        }

        return $a;
    }
}
