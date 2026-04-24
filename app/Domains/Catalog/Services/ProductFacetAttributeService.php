<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Support\Facades\Cache;

class ProductFacetAttributeService extends BaseService
{
    public function __construct()
    {
        // Avoids OOM from Dependencies trait recursive new BaseService()
    }

    /** @param string[] $valueIds */
    public function sync(Product $product, array $valueIds): void
    {
        // Delegates to Product::syncAttributeValues which handles:
        // 1. Eloquent sync on the attributeValues pivot
        // 2. Recomputing the denormalized products.attribute_value_ids GIN column
        $product->syncAttributeValues(array_unique($valueIds));

        $store = config('cache.default');
        if (in_array($store, ['redis', 'memcached'], true)) {
            Cache::tags(['facets'])->flush();
        }
    }

    /** @return string[] */
    public function listValueIds(Product $product): array
    {
        return $product->attributeValues()->pluck('attribute_values.id')->map(fn ($v) => (string) $v)->all();
    }
}
