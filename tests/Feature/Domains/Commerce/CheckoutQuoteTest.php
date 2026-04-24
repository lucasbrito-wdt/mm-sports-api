<?php

use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Enums\PersonalizationOptionType;
use App\Domains\Catalog\Models\ProductPersonalizationOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceFixtures;

uses(RefreshDatabase::class);

it('retorna cotação com totais e linhas', function () {
    $user = User::factory()->create();
    [, $v] = CommerceFixtures::publishedProductWithVariant();
    $res = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/checkout/quote', [
            'items' => [
                ['product_variant_id' => (string) $v->id, 'quantity' => 1],
            ],
            'destination_postal_code' => '01310100',
        ]);
    $res->assertOk();
    $res->assertJsonStructure([
        'subtotal',
        'discount_total',
        'shipping_total',
        'grand_total',
        'lines' => [
            [
                'product_variant_id',
                'quantity',
            ],
        ],
    ]);
    $res->assertJsonPath('lines.0.product_variant_id', (string) $v->id);
});

it('cotação com duas linhas (variantes distintas) soma subtotais', function () {
    $user = User::factory()->create();
    [, $v1] = CommerceFixtures::publishedProductWithVariant('Item A');
    [, $v2] = CommerceFixtures::publishedProductWithVariant('Item B');
    $res = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/checkout/quote', [
            'items' => [
                ['product_variant_id' => (string) $v1->id, 'quantity' => 1],
                ['product_variant_id' => (string) $v2->id, 'quantity' => 2],
            ],
            'destination_postal_code' => '01310100',
        ]);
    $res->assertOk();
    $res->assertJsonCount(2, 'lines');
    $line0 = 99.90 * 1;
    $line1 = 99.90 * 2;
    $subtotal = round($line0 + $line1, 2);
    $res->assertJsonPath('subtotal', $subtotal);
    expect($res->json('lines.0.sku'))->not->toBe($res->json('lines.1.sku'));
});

it('cotação soma preço de personalização por unidade', function () {
    $user = User::factory()->create();
    [$p, $v] = CommerceFixtures::publishedProductWithVariant();
    $p->update(['allows_personalization' => true]);
    $opt = ProductPersonalizationOption::query()->create([
        'product_id' => $p->id,
        'type' => PersonalizationOptionType::ShortText,
        'label' => 'Nome',
        'is_required' => true,
        'additional_price' => 15.5,
        'max_length' => 30,
        'options_json' => null,
        'sort_order' => 0,
    ]);
    $base = 99.90;
    $unit = round($base + 15.5, 2);
    $res = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/checkout/quote', [
            'items' => [[
                'product_variant_id' => (string) $v->id,
                'quantity' => 2,
                'personalization' => [
                    ['option_id' => (string) $opt->id, 'value' => 'João'],
                ],
            ]],
            'destination_postal_code' => '01310100',
        ]);
    $res->assertOk();
    $res->assertJsonPath('lines.0.base_unit_price', $base);
    $res->assertJsonPath('lines.0.unit_price', $unit);
    $res->assertJsonPath('lines.0.line_total', round($unit * 2, 2));
    $res->assertJsonPath('lines.0.personalization_snapshot.0.label', 'Nome');
});

it('rejeita cotação sem opção de personalização obrigatória', function () {
    $user = User::factory()->create();
    [$p, $v] = CommerceFixtures::publishedProductWithVariant();
    $p->update(['allows_personalization' => true]);
    ProductPersonalizationOption::query()->create([
        'product_id' => $p->id,
        'type' => PersonalizationOptionType::ShortText,
        'label' => 'Obrigatório',
        'is_required' => true,
        'additional_price' => 0,
        'max_length' => 10,
        'options_json' => null,
        'sort_order' => 0,
    ]);
    $res = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/checkout/quote', [
            'items' => [
                ['product_variant_id' => (string) $v->id, 'quantity' => 1],
            ],
            'destination_postal_code' => '01310100',
        ]);
    $res->assertStatus(422);
});
