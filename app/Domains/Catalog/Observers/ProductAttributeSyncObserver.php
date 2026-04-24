<?php

namespace App\Domains\Catalog\Observers;

use App\Domains\Catalog\Models\Product;

class ProductAttributeSyncObserver
{
    /** Recalcula `products.attribute_value_ids` a partir do pivô de facetas. */
    public static function recomputeAttributeValueIds(Product $product): void
    {
        $ids = $product->attributeValues()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->sort()
            ->values()
            ->all();

        $product->attribute_value_ids = $ids;
        $product->saveQuietly();
    }
}
