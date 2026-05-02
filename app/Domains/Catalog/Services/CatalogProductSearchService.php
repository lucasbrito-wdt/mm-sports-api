<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogProductSearchService extends BaseService
{
    public function __construct(
        private readonly Product $product,
    ) {
        $this->setModel($this->product);
    }

    /**
     * Listagem de produtos publicados com filtros de facetas, eixo variante e texto.
     *
     * @param  array<string, mixed>  $filters
     */
    public function searchProducts(array $filters, int $page = 1, int $perPage = 24): Collection
    {
        $page = (int) ($filters['page'] ?? $page);
        $perPage = (int) ($filters['per_page'] ?? $perPage);
        $page = max(1, $page);
        $perPage = max(1, min(60, $perPage));

        $hash = sha1(json_encode([$filters, $page, $perPage]));
        $key = "catalog:list:{$hash}";
        $ttl = now()->addSeconds(60);
        $callback = fn () => $this->runQuery($filters, $page, $perPage);

        $store = config('cache.default');
        if (in_array($store, ['redis', 'memcached'], true)) {
            return $this->rememberTaggedWithStampedeLock($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Evita cache stampede em listagens quando várias requisições missam ao mesmo tempo.
     *
     * @param  \Closure(): Collection<int, Product>  $callback
     * @return Collection<int, Product>
     */
    private function rememberTaggedWithStampedeLock(string $key, \DateTimeInterface $ttl, \Closure $callback): Collection
    {
        $cached = Cache::tags(['facets'])->get($key);
        if ($cached instanceof Collection) {
            return $cached;
        }

        $lock = Cache::lock($key.':stampede', 30);

        try {
            return $lock->block(10, function () use ($key, $ttl, $callback) {
                $cached = Cache::tags(['facets'])->get($key);
                if ($cached instanceof Collection) {
                    return $cached;
                }
                $value = $callback();
                Cache::tags(['facets'])->put($key, $value, $ttl);

                return $value;
            });
        } catch (LockTimeoutException) {
            return $callback();
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function runQuery(array $filters, int $page, int $perPage): Collection
    {
        $q = isset($filters['q']) && is_string($filters['q']) ? trim($filters['q']) : null;
        if ($q === '') {
            $q = null;
        }

        $facetFilters = $filters;
        unset($facetFilters['q'], $facetFilters['page'], $facetFilters['per_page']);

        $attrs = Attribute::query()
            ->whereIn('code', array_keys($facetFilters))
            ->get()
            ->keyBy('code');

        $facetValueIds = [];
        $variantValueIds = [];

        foreach ($facetFilters as $code => $slugs) {
            $attr = $attrs[$code] ?? null;
            if (! $attr) {
                continue;
            }
            $ids = AttributeValue::query()
                ->where('attribute_id', $attr->id)
                ->whereIn('slug', (array) $slugs)
                ->pluck('id')
                ->all();

            if ($attr->type->isVariant() && ! $attr->type->isFacet()) {
                $variantValueIds = array_merge($variantValueIds, $ids);
            } else {
                $facetValueIds = array_merge($facetValueIds, $ids);
            }
        }

        $query = Product::query()->where('status', ProductStatus::Published);

        if ($facetValueIds !== []) {
            $this->applyProductFacetContainment($query, $facetValueIds);
        }

        if ($variantValueIds !== []) {
            $this->applyVariantFacetContainment($query, $variantValueIds);
        }

        if ($q !== null) {
            $this->applyTextSearch($query, $q);
        }

        return $query
            ->with(['images'])
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @param  list<string>  $valueIds
     */
    private function applyProductFacetContainment($query, array $valueIds): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $placeholders = implode(',', array_fill(0, count($valueIds), '?'));
            $query->whereRaw(
                "attribute_value_ids @> ARRAY[{$placeholders}]::text[]",
                $valueIds
            );

            return;
        }

        foreach ($valueIds as $id) {
            $query->whereJsonContains('attribute_value_ids', $id);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @param  list<string>  $valueIds
     */
    private function applyVariantFacetContainment($query, array $valueIds): void
    {
        $query->whereExists(function ($sub) use ($valueIds) {
            $sub->select(DB::raw(1))
                ->from('product_variants as v')
                ->whereColumn('v.product_id', 'products.id')
                ->where('v.is_active', true)
                ->where('v.stock_quantity', '>', 0);

            if (DB::getDriverName() === 'pgsql') {
                $placeholders = implode(',', array_fill(0, count($valueIds), '?'));
                $sub->whereRaw(
                    "v.attribute_value_ids @> ARRAY[{$placeholders}]::text[]",
                    $valueIds
                );

                return;
            }

            foreach ($valueIds as $id) {
                $sub->whereJsonContains('v.attribute_value_ids', $id);
            }
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     */
    private function applyTextSearch($query, string $term): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $query->whereRaw("search_tsv @@ plainto_tsquery('portuguese', ?)", [$term]);

            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(function ($qq) use ($like) {
            $qq->where('title', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }
}
