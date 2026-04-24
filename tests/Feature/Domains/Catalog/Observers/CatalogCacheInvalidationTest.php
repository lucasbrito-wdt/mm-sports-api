<?php

use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use Illuminate\Support\Facades\Cache;

it('flushes the facets cache tag when a product is saved', function () {
    Cache::tags(['facets'])->put('catalog:facets:v1', ['cached' => true], 60);
    expect(Cache::tags(['facets'])->get('catalog:facets:v1'))->not->toBeNull();

    Product::create([
        'title' => 'Novo',
        'slug' => 'novo-inv',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);

    expect(Cache::tags(['facets'])->get('catalog:facets:v1'))->toBeNull();
});
