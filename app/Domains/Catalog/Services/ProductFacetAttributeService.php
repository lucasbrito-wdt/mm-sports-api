<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductFacetAttributeService extends BaseService
{
    public function __construct()
    {
        // Avoids OOM from Dependencies trait recursive new BaseService()
    }

    /** @param string[] $valueIds */
    public function sync(Product $product, array $valueIds): void
    {
        DB::transaction(function () use ($product, $valueIds) {
            DB::table('product_attribute_values')->where('product_id', $product->id)->delete();
            if (count($valueIds) === 0) {
                return;
            }
            $rows = array_map(fn ($vid) => [
                'product_id'         => $product->id,
                'attribute_value_id' => (string) $vid,
            ], array_unique($valueIds));
            DB::table('product_attribute_values')->insert($rows);
        });

        $store = config('cache.default');
        if (in_array($store, ['redis', 'memcached'], true)) {
            Cache::tags(['facets'])->flush();
        }
    }

    /** @return string[] */
    public function listValueIds(Product $product): array
    {
        return DB::table('product_attribute_values')
            ->where('product_id', $product->id)
            ->pluck('attribute_value_id')
            ->map(fn ($v) => (string) $v)
            ->all();
    }
}
