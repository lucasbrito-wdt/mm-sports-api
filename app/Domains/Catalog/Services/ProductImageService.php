<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductImageService extends BaseService
{
    public function __construct()
    {
        // Não chama parent::__construct() para evitar recursão do trait Dependencies
    }

    public function listByProduct(string $productId): Collection
    {
        return ProductImage::where('product_id', $productId)
            ->orderBy('display_order')
            ->get();
    }

    public function createForProduct(string $productId, array $data): ProductImage
    {
        $exists = ProductImage::where('product_id', $productId)->exists();
        $maxOrder = $exists ? (int) ProductImage::where('product_id', $productId)->max('display_order') + 1 : 0;

        return ProductImage::create([
            'product_id'         => $productId,
            'url'                => $data['url'],
            'alt'                => $data['alt'] ?? null,
            'attribute_value_id' => $data['attribute_value_id'] ?? null,
            'display_order'      => $data['display_order'] ?? $maxOrder,
        ]);
    }

    public function updateOne(string $imageId, array $data): ProductImage
    {
        $img = ProductImage::findOrFail($imageId);
        $img->update(array_filter([
            'alt'                => $data['alt'] ?? null,
            'attribute_value_id' => array_key_exists('attribute_value_id', $data) ? $data['attribute_value_id'] : $img->attribute_value_id,
            'display_order'      => $data['display_order'] ?? $img->display_order,
        ], fn ($v) => $v !== null) + ['updated_at' => now()]);
        return $img->refresh();
    }

    public function deleteOne(string $imageId): void
    {
        ProductImage::findOrFail($imageId)->delete();
    }

    /** @param string[] $ids */
    public function reorder(string $productId, array $ids): void
    {
        DB::transaction(function () use ($productId, $ids) {
            foreach ($ids as $i => $id) {
                ProductImage::where('product_id', $productId)
                    ->where('id', $id)
                    ->update(['display_order' => $i]);
            }
        });
    }
}
