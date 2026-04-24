<?php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;

it('recomputes products.attribute_value_ids when facets change', function () {
    $brand = Attribute::create(['code' => 'brand', 'label' => 'Marca', 'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select]);
    $nike = AttributeValue::create(['attribute_id' => $brand->id, 'value' => 'Nike', 'slug' => 'nike']);
    $adi = AttributeValue::create(['attribute_id' => $brand->id, 'value' => 'Adidas', 'slug' => 'adidas']);

    $product = Product::create([
        'title' => 'Camisa',
        'slug' => 'cam-obs',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);

    $product->syncAttributeValues([$nike->id]);
    $product->refresh();
    expect($product->attribute_value_ids)->toBe([(string) $nike->id]);

    $product->syncAttributeValues([$nike->id, $adi->id]);
    $product->refresh();
    expect($product->attribute_value_ids)->toEqualCanonicalizing([(string) $nike->id, (string) $adi->id]);
});
