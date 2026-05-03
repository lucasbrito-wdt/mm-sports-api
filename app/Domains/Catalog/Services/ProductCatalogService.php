<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Category;
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
     * @param  string|null  $categoryId  When set, restricts to products in that category (ULID).
     */
    public function listPublished(?string $search = null, ?string $categoryId = null): array
    {
        $query = $this->product->newQuery()
            ->where('status', ProductStatus::Published);

        if ($categoryId !== null && $categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

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
                'images',
            ])
            ->orderBy('title')
            ->get();

        return [
            'data' => $items->map(fn (Product $p) => $this->transformProduct($p))->all(),
        ];
    }

    /**
     * Payload agregado para a home: destaques (mix de produtos publicados) + até N
     * blocos por categoria (slugs em config), cada um com lista limitada.
     *
     * @param  int|null  $categoryBlocks  Quantidade de blocos de categoria (2–5); null usa o default do config.
     * @return array{destaques: array{data: array<int, array<string, mixed>>}, sections: array<int, array{category: array{id: string, name: string, slug: string}, products: array{data: array<int, array<string, mixed>>}}>, meta: array<string, int>}
     */
    public function homeShowcase(
        ?int $categoryBlocks = null,
        int $productsPerSection = 12,
        int $destaquesLimit = 12,
    ): array {
        $defaultBlocks = (int) config('mm_storefront.home.category_blocks_default', 4);
        $n = $categoryBlocks ?? $defaultBlocks;
        $n = max(2, min(5, $n));

        $productsPerSection = max(1, min(48, $productsPerSection));
        $destaquesLimit = max(1, min(48, $destaquesLimit));

        $slugs = config('mm_storefront.home.section_slugs', []);
        $slugs = array_slice($slugs, 0, $n);

        $with = [
            'variants' => fn ($q) => $q->where('is_active', true),
            'sizeChart',
            'images',
        ];

        $destaquesItems = $this->product->newQuery()
            ->where('status', ProductStatus::Published)
            ->with($with)
            ->orderByDesc('updated_at')
            ->limit($destaquesLimit)
            ->get();

        $destaques = $destaquesItems
            ->map(fn (Product $p) => $this->transformProduct($p))
            ->values()
            ->all();

        $sections = [];
        foreach ($slugs as $slug) {
            $category = Category::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();

            if ($category === null) {
                continue;
            }

            $catProducts = $this->product->newQuery()
                ->where('status', ProductStatus::Published)
                ->where('category_id', $category->id)
                ->with($with)
                ->orderBy('title')
                ->limit($productsPerSection)
                ->get();

            $sections[] = [
                'category' => [
                    'id' => (string) $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ],
                'products' => [
                    'data' => $catProducts
                        ->map(fn (Product $p) => $this->transformProduct($p))
                        ->values()
                        ->all(),
                ],
            ];
        }

        return [
            'destaques' => ['data' => $destaques],
            'sections' => $sections,
            'meta' => [
                'category_blocks_requested' => $n,
                'category_blocks_returned' => count($sections),
                'destaques_count' => count($destaques),
                'products_per_section' => $productsPerSection,
            ],
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
            ->where(fn ($q) => $q->where('id', $id)->orWhere('slug', $id))
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true),
                'personalizationOptions',
                'sizeChart',
                'images',
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
        $images = $this->transformImages($p);

        $out = [
            'id' => (string) $p->id,
            'title' => $p->title,
            'slug' => $p->slug,
            'description' => $p->description,
            'category_id' => $p->category_id,
            'origin' => $p->origin->value,
            'allows_personalization' => $p->allows_personalization,
            'ncm' => $p->ncm,
            'meta_title' => $p->meta_title,
            'meta_description' => $p->meta_description,
            'size_chart' => $this->transformSizeChart($p),
            'images' => $images,
            'primary_image_url' => $images[0]['url'] ?? null,
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function transformImages(Product $p): array
    {
        if (! $p->relationLoaded('images')) {
            return [];
        }

        // Convention: lowest display_order is the primary image.
        $sorted = $p->images->sortBy('display_order')->values();

        return $sorted->map(fn ($img, int $idx) => [
            'id' => (string) $img->id,
            'url' => $img->url,
            'alt' => $img->alt,
            'sort_order' => (int) $img->display_order,
            'is_primary' => $idx === 0,
        ])->all();
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
