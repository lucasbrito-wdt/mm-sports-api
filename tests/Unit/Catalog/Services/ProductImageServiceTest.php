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

it('updateOne clears alt when null is explicitly passed', function () {
    $product = Product::create(['title' => 'P', 'slug' => 'p-upd-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT), 'origin' => 'national', 'status' => 'draft']);
    $img = ProductImage::create(['product_id' => $product->id, 'url' => 'https://x/img.jpg', 'alt' => 'original alt', 'display_order' => 0]);

    $updated = app(ProductImageService::class)->updateOne($img->id, ['alt' => null]);

    expect($updated->alt)->toBeNull();
});

it('updateOne disassociates attribute_value when null is explicitly passed', function () {
    $product = Product::create(['title' => 'P', 'slug' => 'p-disassoc-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT), 'origin' => 'national', 'status' => 'draft']);
    $img = ProductImage::create(['product_id' => $product->id, 'url' => 'https://x/img.jpg', 'attribute_value_id' => null, 'display_order' => 0]);

    $updated = app(ProductImageService::class)->updateOne($img->id, ['attribute_value_id' => null]);

    expect($updated->attribute_value_id)->toBeNull();
});

it('updateOne without fields preserves existing values', function () {
    $product = Product::create(['title' => 'P', 'slug' => 'p-noop-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT), 'origin' => 'national', 'status' => 'draft']);
    $img = ProductImage::create(['product_id' => $product->id, 'url' => 'https://x/img.jpg', 'alt' => 'keep me', 'display_order' => 3]);

    $updated = app(ProductImageService::class)->updateOne($img->id, []);

    expect($updated->alt)->toBe('keep me')
        ->and($updated->display_order)->toBe(3);
});

it('deleteOne removes the image', function () {
    $product = Product::create(['title' => 'P', 'slug' => 'p-del-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT), 'origin' => 'national', 'status' => 'draft']);
    $img = ProductImage::create(['product_id' => $product->id, 'url' => 'https://x/img.jpg', 'display_order' => 0]);

    app(ProductImageService::class)->deleteOne($img->id);

    expect(ProductImage::find($img->id))->toBeNull();
});
