<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Observers\ProductAttributeSyncObserver;
use App\Domains\Catalog\Observers\VariantAttributeSyncObserver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RebuildCatalogCacheService
{
    public function handle(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->rebuildProductAttributeCachesPgsql();
            if (! app()->runningUnitTests()) {
                $this->refreshFacetCountsMaterializedView();
            }
        } else {
            $this->rebuildProductCachesEloquent();
        }

        $this->rebuildVariantCachesFromPivots();

        $this->flushFacetRelatedCache();
    }

    private function rebuildProductAttributeCachesPgsql(): void
    {
        DB::statement('
            UPDATE products p SET attribute_value_ids = COALESCE(sub.ids, \'{}\'::text[])
            FROM (
                SELECT product_id, array_agg(attribute_value_id ORDER BY attribute_value_id) AS ids
                FROM product_attribute_values
                GROUP BY product_id
            ) sub
            WHERE sub.product_id = p.id
        ');

        DB::statement('
            UPDATE products SET attribute_value_ids = \'{}\'::text[]
            WHERE id NOT IN (SELECT product_id FROM product_attribute_values)
        ');
    }

    private function refreshFacetCountsMaterializedView(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::statement('REFRESH MATERIALIZED VIEW product_facet_counts');

            return;
        }

        try {
            DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY product_facet_counts');
        } catch (\Throwable) {
            DB::statement('REFRESH MATERIALIZED VIEW product_facet_counts');
        }
    }

    private function rebuildProductCachesEloquent(): void
    {
        Product::query()->orderBy('id')->each(function (Product $product) {
            ProductAttributeSyncObserver::recomputeAttributeValueIds($product);
        });
    }

    private function rebuildVariantCachesFromPivots(): void
    {
        ProductVariant::query()->orderBy('id')->each(function (ProductVariant $variant) {
            VariantAttributeSyncObserver::recomputeFromPivots($variant);
        });
    }

    private function flushFacetRelatedCache(): void
    {
        $store = config('cache.default');
        if (in_array($store, ['redis', 'memcached'], true)) {
            Cache::tags(['facets'])->flush();
        }
    }
}
