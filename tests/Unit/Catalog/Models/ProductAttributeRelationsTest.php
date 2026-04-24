<?php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('attaches attribute values to a product', function () {
    $brand = Attribute::create([
        'code' => 'brand', 'label' => 'Marca',
        'type' => AttributeType::Facet,
        'input_type' => AttributeInputType::Select,
    ]);
    $nike = AttributeValue::create([
        'attribute_id' => $brand->id, 'value' => 'Nike', 'slug' => 'nike',
    ]);

    $product = Product::create([
        'title' => 'Camisa',
        'slug' => 'camisa',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);
    $product->attributeValues()->attach($nike->id);

    expect($product->attributeValues)->toHaveCount(1);
    expect($product->attributeValues->first()->slug)->toBe('nike');
});

it('declares variant axes on a product', function () {
    $color = Attribute::create([
        'code' => 'color', 'label' => 'Cor',
        'type' => AttributeType::Variant,
        'input_type' => AttributeInputType::Swatch,
    ]);

    $product = Product::create([
        'title' => 'Camisa',
        'slug' => 'camisa-axes',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);
    $product->variantAxes()->attach($color->id, ['display_order' => 0]);

    expect($product->variantAxes)->toHaveCount(1);
    expect($product->variantAxes->first()->code)->toBe('color');
});

it('has images keyed by attribute value', function () {
    $product = Product::create([
        'title' => 'Camisa',
        'slug' => 'camisa-img',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);
    ProductImage::create([
        'product_id' => $product->id,
        'url' => 'https://cdn/example.jpg',
        'display_order' => 0,
    ]);

    expect($product->images)->toHaveCount(1);
    expect($product->images->first()->attribute_value_id)->toBeNull();
});
