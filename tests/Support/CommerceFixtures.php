<?php

namespace Tests\Support;

use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Support\Str;

final class CommerceFixtures
{
    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    public static function publishedProductWithVariant(string $title = 'Kit'): array
    {
        $p = Product::query()->create([
            'title' => $title,
            'slug' => 'p-'.Str::lower(Str::random(10)),
            'description' => null,
            'origin' => ProductOrigin::National,
            'allows_personalization' => false,
            'size_chart_id' => null,
            'status' => ProductStatus::Published,
            'ncm' => null,
            'meta_title' => null,
            'meta_description' => null,
        ]);

        $v = ProductVariant::query()->create([
            'product_id' => $p->id,
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'price' => 99.90,
            'compare_at_price' => null,
            'stock_quantity' => 1,
            'weight_grams' => 200,
            'length_cm' => null,
            'width_cm' => null,
            'height_cm' => null,
            'attribute_payload' => ['size' => 'M'],
            'is_active' => true,
        ]);

        return [$p, $v];
    }

    /**
     * Payload mínimo para `POST /api/orders` no contrato autenticado.
     */
    public static function orderPayload(array $items, array $overrides = []): array
    {
        return array_merge([
            'billing_type' => 'PIX',
            'items' => $items,
            'address' => [
                'postal_code' => '01310100',
                'street' => 'Av. Paulista',
                'number' => '1000',
                'district' => 'Bela Vista',
                'city' => 'São Paulo',
                'state' => 'SP',
            ],
        ], $overrides);
    }
}
