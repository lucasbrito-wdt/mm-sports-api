<?php

use App\Domains\Catalog\Enums\PersonalizationOptionType;
use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductPersonalizationOption;
use App\Domains\Catalog\Models\SizeChart;
use App\Domains\Tracking\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CommerceFixtures;

uses(RefreshDatabase::class);

it('lista produtos publicados com variantes ativas', function () {
    [$product] = CommerceFixtures::publishedProductWithVariant('Kit');
    $category = Category::query()->create([
        'name' => 'Kits',
        'slug' => 'kits-'.Str::lower(Str::random(6)),
    ]);
    $product->update(['category_id' => $category->id]);

    $res = $this->getJson('/api/products');
    $res->assertOk();
    $res->assertJsonPath('data.0.title', 'Kit');
    $res->assertJsonPath('data.0.category_id', (string) $category->id);
});

it('filtra a listagem com q e regista eventos de analytics', function () {
    CommerceFixtures::publishedProductWithVariant('Camiseta Alpha');
    Product::query()->create([
        'title' => 'Outro',
        'slug' => 'outro-'.Str::lower(Str::random(6)),
        'description' => null,
        'origin' => ProductOrigin::National,
        'allows_personalization' => false,
        'size_chart_id' => null,
        'status' => ProductStatus::Published,
        'ncm' => null,
        'meta_title' => null,
        'meta_description' => null,
    ]);

    $res = $this->getJson('/api/products?q=Alpha');
    $res->assertOk();
    $res->assertJsonCount(1, 'data');
    $res->assertJsonPath('data.0.title', 'Camiseta Alpha');

    expect(AnalyticsEvent::query()->where('name', 'search_executed')->exists())->toBeTrue();
    expect(AnalyticsEvent::query()->where('name', 'product_list_viewed')->exists())->toBeTrue();
});

it('detalhe público inclui tabela de medidas e opções de personalização', function () {
    [$product] = CommerceFixtures::publishedProductWithVariant('Com Guia');
    $category = Category::query()->create([
        'name' => 'Camisas',
        'slug' => 'camisas-'.Str::lower(Str::random(6)),
    ]);
    $chart = SizeChart::query()->create([
        'name' => 'Medidas',
        'table_json' => ['headers' => ['P'], 'rows' => [['40']]],
    ]);
    $product->update([
        'size_chart_id' => $chart->id,
        'category_id' => $category->id,
        'allows_personalization' => true,
    ]);
    ProductPersonalizationOption::query()->create([
        'product_id' => $product->id,
        'type' => PersonalizationOptionType::ShortText,
        'label' => 'Nome',
        'is_required' => true,
        'additional_price' => 10,
        'max_length' => 12,
        'options_json' => null,
        'sort_order' => 0,
    ]);

    $res = $this->getJson("/api/products/{$product->id}");
    $res->assertOk();
    $res->assertJsonPath('category_id', $category->id);
    $res->assertJsonPath('size_chart.name', 'Medidas');
    $res->assertJsonPath('size_chart.table_json.headers.0', 'P');
    $res->assertJsonPath('personalization_options.0.label', 'Nome');
    $res->assertJsonPath('personalization_options.0.type', 'short_text');
});

it('listagem pública inclui size_chart nulo quando não definido', function () {
    CommerceFixtures::publishedProductWithVariant('Só variante');
    $res = $this->getJson('/api/products');
    $res->assertOk();
    $res->assertJsonPath('data.0.size_chart', null);
    $res->assertJsonPath('data.0.personalization_options', []);
});

it('retorna 404 para produto não publicado', function () {
    $p = Product::query()->create([
        'title' => 'Rascunho',
        'slug' => 'draft-'.Str::lower(Str::random(8)),
        'description' => null,
        'origin' => ProductOrigin::National,
        'allows_personalization' => false,
        'size_chart_id' => null,
        'status' => ProductStatus::Draft,
        'ncm' => null,
        'meta_title' => null,
        'meta_description' => null,
    ]);
    $this->getJson("/api/products/{$p->id}")->assertNotFound();
});
