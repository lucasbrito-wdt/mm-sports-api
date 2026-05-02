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
        $currentMax = ProductImage::where('product_id', $productId)->max('display_order');
        $nextOrder = $currentMax !== null ? (int) $currentMax + 1 : 0;

        return ProductImage::create([
            'product_id'         => $productId,
            'url'                => $data['url'],
            'alt'                => $data['alt'] ?? null,
            'attribute_value_id' => $data['attribute_value_id'] ?? null,
            'display_order'      => $data['display_order'] ?? $nextOrder,
        ]);
    }

    public function updateOne(string $imageId, array $data): ProductImage
    {
        $img = ProductImage::findOrFail($imageId);

        $payload = [];
        if (array_key_exists('alt', $data)) {
            $payload['alt'] = $data['alt'];
        }
        if (array_key_exists('attribute_value_id', $data)) {
            $payload['attribute_value_id'] = $data['attribute_value_id'];
        }
        if (array_key_exists('display_order', $data)) {
            $payload['display_order'] = $data['display_order'];
        }

        if (! empty($payload)) {
            $img->update($payload);
        }

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
