<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Shared\Services\BaseService;

class ProductCatalogService extends BaseService
{
    public function __construct(
        private readonly Product $product,
    ) {
        $this->setModel($this->product);
    }

    /**
     * @param  string|null  $search  Plain search; filters title/slug (LIKE). Empty string = no filter.
     */
    public function listPublished(?string $search = null): array
    {
        $query = $this->product->newQuery()
            ->where('status', ProductStatus::Published);

        if ($search !== null && $search !== '') {
            $term = '%'.$this->escapeLike($search).'%';
            $query->where(function ($w) use ($term) {
                $w->where('title', 'like', $term)
                    ->orWhere('slug', 'like', $term);
            });
        }

        $items = $query
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true),
                'sizeChart',
            ])
            ->orderBy('title')
            ->get();

        return [
            'data' => $items->map(fn (Product $p) => $this->transformProduct($p))->all(),
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    public function showPublished(string $id): ?array
    {
        $product = $this->product->newQuery()
            ->where('status', ProductStatus::Published)
            ->where('id', $id)
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true),
                'personalizationOptions',
                'sizeChart',
            ])
            ->first();

        return $product ? $this->transformProduct($product) : null;
    }

    public function findPublished(string $id): ?Product
    {
        return $this->product->newQuery()
            ->where('status', ProductStatus::Published)
            ->where('id', $id)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->first();
    }

    private function transformProduct(Product $p): array
    {
        $out = [
            'id' => (string) $p->id,
            'title' => $p->title,
            'slug' => $p->slug,
            'description' => $p->description,
            'origin' => $p->origin->value,
            'allows_personalization' => $p->allows_personalization,
            'ncm' => $p->ncm,
            'meta_title' => $p->meta_title,
            'meta_description' => $p->meta_description,
            'size_chart' => $this->transformSizeChart($p),
            'personalization_options' => [],
            'variants' => $p->variants->map(fn ($v) => [
                'id' => (string) $v->id,
                'sku' => $v->sku,
                'price' => $v->price,
                'compare_at_price' => $v->compare_at_price,
                'stock_quantity' => $v->stock_quantity,
                'weight_grams' => $v->weight_grams,
                'length_cm' => $v->length_cm,
                'width_cm' => $v->width_cm,
                'height_cm' => $v->height_cm,
                'attribute_payload' => $v->attribute_payload,
                'is_active' => $v->is_active,
            ])->all(),
        ];

        if ($p->relationLoaded('personalizationOptions')) {
            $out['personalization_options'] = $p->personalizationOptions
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($o) => [
                    'id' => (string) $o->id,
                    'type' => $o->type->value,
                    'label' => $o->label,
                    'is_required' => $o->is_required,
                    'additional_price' => $o->additional_price,
                    'max_length' => $o->max_length,
                    'options_json' => $o->options_json,
                    'sort_order' => $o->sort_order,
                ])->all();
        }

        return $out;
    }

    private function transformSizeChart(Product $p): ?array
    {
        if (! $p->relationLoaded('sizeChart')) {
            return null;
        }

        if ($p->sizeChart === null) {
            return null;
        }

        $c = $p->sizeChart;

        return [
            'id' => (string) $c->id,
            'name' => $c->name,
            'table_json' => $c->table_json,
        ];
    }
}
