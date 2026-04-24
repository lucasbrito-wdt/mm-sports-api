<?php

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Services\ProductImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates image with default display_order at end', function () {
    $product = Product::create(['title' => 'P', 'slug' => 'p-img-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT), 'origin' => 'national', 'status' => 'draft']);
    ProductImage::create(['product_id' => $product->id, 'url' => 'https://x/1.jpg', 'display_order' => 0]);
    ProductImage::create(['product_id' => $product->id, 'url' => 'https://x/2.jpg', 'display_order' => 1]);

    $img = app(ProductImageService::class)->createForProduct($product->id, [
        'url' => 'https://x/3.jpg',
        'alt' => null,
        'attribute_value_id' => null,
    ]);

    expect($img->display_order)->toBe(2);
});

it('reorder updates display_order atomically', function () {
    $product = Product::create(['title' => 'P', 'slug' => 'p-reorder-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT), 'origin' => 'national', 'status' => 'draft']);
    $a = ProductImage::create(['product_id' => $product->id, 'url' => 'https://x/a.jpg', 'display_order' => 0]);
    $b = ProductImage::create(['product_id' => $product->id, 'url' => 'https://x/b.jpg', 'display_order' => 1]);
    $c = ProductImage::create(['product_id' => $product->id, 'url' => 'https://x/c.jpg', 'display_order' => 2]);

    app(ProductImageService::class)->reorder($product->id, [$c->id, $a->id, $b->id]);

    expect($c->refresh()->display_order)->toBe(0)
        ->and($a->refresh()->display_order)->toBe(1)
        ->and($b->refresh()->display_order)->toBe(2);
});
