<?php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\CatalogProductSearchService;

it('filters by facet slugs via GIN array containment', function () {
    $brand = Attribute::create(['code' => 'brand', 'label' => 'Marca', 'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select]);
    $team = Attribute::create(['code' => 'team', 'label' => 'Time', 'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select]);

    $nike = AttributeValue::create(['attribute_id' => $brand->id, 'value' => 'Nike', 'slug' => 'nike']);
    $cor = AttributeValue::create(['attribute_id' => $team->id, 'value' => 'Corinthians', 'slug' => 'corinthians']);
    $pal = AttributeValue::create(['attribute_id' => $team->id, 'value' => 'Palmeiras', 'slug' => 'palmeiras']);

    $match = Product::create([
        'title' => 'Camisa Nike Timão',
        'slug' => 'a',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);
    $match->syncAttributeValues([$nike->id, $cor->id]);

    $skip = Product::create([
        'title' => 'Camisa Nike Verdão',
        'slug' => 'b',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);
    $skip->syncAttributeValues([$nike->id, $pal->id]);

    $results = app(CatalogProductSearchService::class)->searchProducts([
        'brand' => ['nike'],
        'team' => ['corinthians'],
    ]);

    expect($results->pluck('slug')->all())->toBe(['a']);
});

it('filters by full-text search via tsvector', function () {
    Product::create([
        'title' => 'Camisa Corinthians oficial',
        'slug' => 'c1',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);
    Product::create([
        'title' => 'Boné preto liso',
        'slug' => 'c2',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);

    $results = app(CatalogProductSearchService::class)->searchProducts(['q' => 'camisa']);

    expect($results->pluck('slug')->all())->toContain('c1');
    expect($results->pluck('slug')->all())->not->toContain('c2');
});
