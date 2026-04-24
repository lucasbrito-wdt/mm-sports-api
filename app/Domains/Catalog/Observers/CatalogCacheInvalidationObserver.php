<?php

namespace App\Domains\Catalog\Observers;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CatalogCacheInvalidationObserver
{
    public function saved(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        $this->safeFlushTags(['facets']);

        $productId = $this->productIdForCacheTags($model);
        if ($productId !== null) {
            $this->safeFlushTags(["product:{$productId}"]);
        }
    }

    /**
     * @param  list<string>  $names
     */
    private function safeFlushTags(array $names): void
    {
        try {
            Cache::tags($names)->flush();
        } catch (\Throwable) {
            // Drivers sem suporte a tags (ex.: file) — listagens usam TTL curto.
        }
    }

    private function productIdForCacheTags(Model $model): ?string
    {
        return match (true) {
            $model instanceof Product => (string) $model->getKey(),
            $model instanceof ProductVariant => (string) $model->getAttribute('product_id'),
            $model instanceof ProductImage => (string) $model->getAttribute('product_id'),
            default => null,
        };
    }
}
