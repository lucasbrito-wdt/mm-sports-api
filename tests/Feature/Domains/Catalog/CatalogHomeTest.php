<?php

use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'mm_storefront.home.section_slugs' => ['selecoes', 'times-europeus'],
        'mm_storefront.home.category_blocks_default' => 2,
        'mm_storefront.home.products_per_section' => 5,
        'mm_storefront.home.destaques_limit' => 8,
    ]);
});

it('retorna destaques e seções no formato esperado', function (): void {
    $cat = Category::query()->create([
        'id' => (string) Str::ulid(),
        'name' => 'Seleções',
        'slug' => 'selecoes',
        'parent_id' => null,
        'is_active' => true,
        'display_order' => 1,
    ]);

    Product::query()->create([
        'id' => (string) Str::ulid(),
        'title' => 'Camisa Brasil',
        'slug' => 'camisa-brasil',
        'origin' => ProductOrigin::National,
        'allows_personalization' => false,
        'status' => ProductStatus::Published,
        'category_id' => $cat->id,
    ]);

    $res = $this->getJson('/api/catalog/home?categories=2');

    $res->assertOk();
    $res->assertJsonStructure([
        'destaques' => ['data'],
        'sections' => [
            '*' => [
                'category' => ['id', 'name', 'slug'],
                'products' => ['data'],
            ],
        ],
        'meta',
    ]);

    expect($res->json('destaques.data'))->toHaveCount(1);
    expect($res->json('sections'))->toHaveCount(1);
    expect($res->json('sections.0.category.slug'))->toBe('selecoes');
});

it('respeita categories entre 2 e 5', function (): void {
    config(['mm_storefront.home.section_slugs' => ['a', 'b', 'c', 'd', 'e', 'f']]);

    foreach (['a', 'b', 'c', 'd', 'e'] as $slug) {
        Category::query()->create([
            'id' => (string) Str::ulid(),
            'name' => strtoupper($slug),
            'slug' => $slug,
            'parent_id' => null,
            'is_active' => true,
            'display_order' => 1,
        ]);
    }

    $res = $this->getJson('/api/catalog/home?categories=5');
    $res->assertOk();
    expect($res->json('sections'))->toHaveCount(5);
});
