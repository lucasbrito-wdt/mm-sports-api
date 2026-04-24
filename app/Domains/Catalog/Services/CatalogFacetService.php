<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogFacetService extends BaseService
{
    public function __construct(
        private readonly Product $product,
    ) {
        $this->setModel($this->product);
    }

    public function getFacets(): array
    {
        $key = 'catalog:facets:v1';
        $ttl = now()->addMinutes(5);
        $callback = fn () => $this->queryFacets();

        $store = config('cache.default');
        if (in_array($store, ['redis', 'memcached'], true)) {
            return Cache::tags(['facets'])->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }

    private function queryFacets(): array
    {
        if ($this->useMaterializedViewForFacets()) {
            return $this->queryFacetsUsingMaterializedView();
        }

        return $this->queryFacetsUsingLiveCounts();
    }

    private function useMaterializedViewForFacets(): bool
    {
        return DB::getDriverName() === 'pgsql' && ! app()->runningUnitTests();
    }

    private function queryFacetsUsingMaterializedView(): array
    {
        $rows = DB::select('
            SELECT a.code, a.label, a.input_type, a.display_order AS a_order,
                   av.id AS value_id, av.value, av.slug, av.metadata,
                   av.display_order AS v_order,
                   COALESCE(fc.product_count, 0) AS product_count
            FROM attributes a
            JOIN attribute_values av ON av.attribute_id = a.id
            LEFT JOIN product_facet_counts fc ON fc.attribute_value_id = av.id
            WHERE a.is_filterable = true
              AND (fc.product_count IS NULL OR fc.product_count > 0)
            ORDER BY a.display_order, av.display_order
        ');

        return $this->groupFacetRows($rows);
    }

    private function queryFacetsUsingLiveCounts(): array
    {
        $rows = DB::select('
            SELECT a.code, a.label, a.input_type, a.display_order AS a_order,
                   av.id AS value_id, av.value, av.slug, av.metadata,
                   av.display_order AS v_order,
                   COALESCE(fc.product_count, 0) AS product_count
            FROM attributes a
            JOIN attribute_values av ON av.attribute_id = a.id
            LEFT JOIN (
                SELECT pav.attribute_value_id, COUNT(DISTINCT p.id) AS product_count
                FROM product_attribute_values pav
                INNER JOIN products p ON p.id = pav.product_id AND p.status = ?
                GROUP BY pav.attribute_value_id
            ) fc ON fc.attribute_value_id = av.id
            WHERE a.is_filterable
              AND (fc.product_count IS NULL OR fc.product_count > 0)
            ORDER BY a.display_order, av.display_order
        ', ['published']);

        return $this->groupFacetRows($rows);
    }

    /**
     * @param  array<int, object>  $rows
     */
    private function groupFacetRows(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r->code] ??= [
                'code' => $r->code,
                'label' => $r->label,
                'input_type' => $r->input_type,
                'values' => [],
            ];

            $metadata = $r->metadata;
            if (is_string($metadata) && $metadata !== '') {
                $metadata = json_decode($metadata, true);
            } elseif ($metadata === '' || $metadata === null) {
                $metadata = null;
            }

            $grouped[$r->code]['values'][] = [
                'id' => (string) $r->value_id,
                'value' => $r->value,
                'slug' => $r->slug,
                'metadata' => is_array($metadata) ? $metadata : null,
                'count' => (int) $r->product_count,
            ];
        }

        return array_values($grouped);
    }
}
