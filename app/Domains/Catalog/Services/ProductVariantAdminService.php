<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Tracking\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProductVariantAdminService extends BaseService
{
    public function __construct(
        private readonly Product $product,
        private readonly ProductVariant $productVariant,
        private readonly AuditLogger $auditLogger,
    ) {
        $this->setModel($this->productVariant);
    }

    public function indexForProduct(string $productId, array $options = [], ?\Closure $builderCallback = null)
    {
        $this->assertProduct($productId);
        $options['filters'] = is_array($options['filters'] ?? null) ? $options['filters'] : [];
        $options['filters']['product_id'] = $productId;
        if (empty($options['sort_by'])) {
            $options['sort_by'] = 'sku';
            $options['sort_order'] = 'asc';
        }

        return parent::index($options, $builderCallback);
    }

    public function showForProduct(string $productId, string $variantId)
    {
        $this->assertProduct($productId);

        $variant = $this->getModel()->newQuery()
            ->where('product_id', $productId)
            ->whereKey($variantId)
            ->firstOrFail();

        return $variant;
    }

    public function storeForProduct(string $productId, array $data)
    {
        return DB::transaction(function () use ($productId, $data) {
            $this->assertProduct($productId);
            $data = $this->onlyFillable($data);
            $data['product_id'] = $productId;
            if (! array_key_exists('is_active', $data)) {
                $data['is_active'] = true;
            }
            /** @var ProductVariant $model */
            $model = $this->getModel()->newQuery()->create($data);
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'product_variants.create',
                $model,
                null,
                $this->auditSnapshot($model),
                request()
            );

            return $model;
        });
    }

    public function updateForProduct(string $productId, string $variantId, array $data)
    {
        return DB::transaction(function () use ($productId, $variantId, $data) {
            $this->assertProduct($productId);
            /** @var ProductVariant $record */
            $record = $this->getModel()->newQuery()
                ->where('product_id', $productId)
                ->whereKey($variantId)
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
                'product_variants.update',
                $record,
                $old,
                $this->auditSnapshot($record),
                request()
            );

            return $record;
        });
    }

    public function destroyForProduct(string $productId, string $variantId)
    {
        return DB::transaction(function () use ($productId, $variantId) {
            $this->assertProduct($productId);
            $record = $this->getModel()->newQuery()
                ->where('product_id', $productId)
                ->whereKey($variantId)
                ->firstOrFail();
            $old = $this->auditSnapshot($record);
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'product_variants.delete',
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
        if (array_key_exists('attribute_payload', $a) && is_string($a['attribute_payload'] ?? null)) {
            $a['attribute_payload'] = $model->getAttribute('attribute_payload');
        }

        return $a;
    }
}
