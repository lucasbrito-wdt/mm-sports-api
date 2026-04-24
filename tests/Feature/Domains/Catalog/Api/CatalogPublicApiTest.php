<?php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;

it('GET /api/catalog/facets returns grouped facets', function () {
    $brand = Attribute::create(['code' => 'brand', 'label' => 'Marca', 'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select]);
    $nike = AttributeValue::create(['attribute_id' => $brand->id, 'value' => 'Nike', 'slug' => 'nike']);
    $p = Product::create([
        'title' => 'Camisa',
        'slug' => 'api-f',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);
    $p->syncAttributeValues([$nike->id]);

    $this->getJson('/api/catalog/facets')
        ->assertOk()
        ->assertJsonPath('facets.0.code', 'brand')
        ->assertJsonPath('facets.0.values.0.slug', 'nike');
});

it('GET /api/catalog/products filters by slug-valued facets', function () {
    $brand = Attribute::create(['code' => 'brand', 'label' => 'Marca', 'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select]);
    $nike = AttributeValue::create(['attribute_id' => $brand->id, 'value' => 'Nike', 'slug' => 'nike']);

    $p = Product::create([
        'title' => 'X',
        'slug' => 'api-x',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);
    $p->syncAttributeValues([$nike->id]);

    $this->getJson('/api/catalog/products?brand=nike')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'api-x']);
});
