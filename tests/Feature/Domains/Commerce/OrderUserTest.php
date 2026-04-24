<?php

use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Enums\PersonalizationOptionType;
use App\Domains\Catalog\Models\ProductPersonalizationOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceFixtures;

uses(RefreshDatabase::class);

it('lista encomendas só do utilizador autenticado', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    [, $v] = CommerceFixtures::publishedProductWithVariant();

    $this->withHeaders(jwtHeaders($u1))
        ->postJson('/api/orders', [
            'items' => [['product_variant_id' => (string) $v->id, 'quantity' => 1]],
            'destination_postal_code' => '01310100',
        ])
        ->assertCreated();

    $list1 = $this->withHeaders(jwtHeaders($u1))->getJson('/api/orders');
    $list1->assertOk();
    expect($list1->json('total'))->toBe(1);

    $list2 = $this->withHeaders(jwtHeaders($u2))->getJson('/api/orders');
    $list2->assertOk();
    expect($list2->json('total'))->toBe(0);
});

it('detalhe da encomenda inclui linhas e personalização para o dono', function () {
    $user = User::factory()->create();
    [$p, $v] = CommerceFixtures::publishedProductWithVariant();
    $p->update(['allows_personalization' => true]);
    $opt = ProductPersonalizationOption::query()->create([
        'product_id' => $p->id,
        'type' => PersonalizationOptionType::ShortText,
        'label' => 'Nome',
        'is_required' => true,
        'additional_price' => 2,
        'max_length' => 20,
        'options_json' => null,
        'sort_order' => 0,
    ]);

    $create = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/orders', [
            'items' => [[
                'product_variant_id' => (string) $v->id,
                'quantity' => 1,
                'personalization' => [
                    ['option_id' => (string) $opt->id, 'value' => 'Teste'],
                ],
            ]],
            'destination_postal_code' => '01310100',
        ]);
    $create->assertCreated();
    $orderId = $create->json('data.id');

    $detail = $this->withHeaders(jwtHeaders($user))->getJson("/api/orders/{$orderId}");
    $detail->assertOk();
    $detail->assertJsonPath('items.0.product_title', $p->title);
    $detail->assertJsonPath('items.0.personalization_snapshot.0.value', 'Teste');
    $detail->assertJsonPath('grand_total', $create->json('data.grand_total'));
});

it('outro utilizador não acede ao detalhe da encomenda', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    [, $v] = CommerceFixtures::publishedProductWithVariant();
    $orderId = $this->withHeaders(jwtHeaders($owner))
        ->postJson('/api/orders', [
            'items' => [['product_variant_id' => (string) $v->id, 'quantity' => 1]],
            'destination_postal_code' => '01310100',
        ])
        ->json('data.id');

    $this->withHeaders(jwtHeaders($other))
        ->getJson("/api/orders/{$orderId}")
        ->assertForbidden();
});

it('retorna 404 se o id da encomenda não existir', function () {
    $user = User::factory()->create();
    $this->withHeaders(jwtHeaders($user))
        ->getJson('/api/orders/01HZ0000000000000000000000')
        ->assertNotFound();
});

it('rejeita listagem e detalhe de encomendas sem autenticação', function () {
    $this->getJson('/api/orders')->assertUnauthorized();
    $this->getJson('/api/orders/01HZ0000000000000000000000')->assertUnauthorized();
});
