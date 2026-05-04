<?php

use App\Domains\Auth\Models\User;
use App\Domains\Commerce\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceFixtures;

uses(RefreshDatabase::class);

it('retorna timeline para o dono do pedido', function () {
    $user = User::factory()->create();
    [, $v] = CommerceFixtures::publishedProductWithVariant();
    $orderRes = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/orders', [
            'items' => [
                ['product_variant_id' => (string) $v->id, 'quantity' => 1],
            ],
            'address' => ['postal_code' => '01310100', 'street' => 'Av. Paulista', 'number' => '1000', 'district' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP'], 'billing_type' => 'PIX',
        ]);
    $orderId = $orderRes->json('order_id');

    $res = $this->withHeaders(jwtHeaders($user))
        ->getJson("/api/orders/{$orderId}/timeline");
    $res->assertOk();
    $res->assertJsonPath('order_id', $orderId);
    $res->assertJsonStructure([
        'order_id',
        'correios_tracking_code',
        'transitions',
    ]);
    expect($res->json('transitions'))->not->toBeEmpty();
});

it('retorna 403 para outro usuário', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    expect((string) $owner->id)->not->toBe((string) $stranger->id);
    [, $v] = CommerceFixtures::publishedProductWithVariant();
    $orderId = $this->withHeaders(jwtHeaders($owner))
        ->postJson('/api/orders', [
            'items' => [
                ['product_variant_id' => (string) $v->id, 'quantity' => 1],
            ],
            'address' => ['postal_code' => '01310100', 'street' => 'Av. Paulista', 'number' => '1000', 'district' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP'], 'billing_type' => 'PIX',
        ])->json('order_id');

    $order = Order::query()->findOrFail($orderId);
    expect((string) $order->user_id)->toBe((string) $owner->id);

    $this->withHeaders(jwtHeaders($stranger))
        ->getJson("/api/orders/{$orderId}/timeline")
        ->assertForbidden();
});

it('retorna 404 em timeline quando o pedido não existe', function () {
    $user = User::factory()->create();
    $this->withHeaders(jwtHeaders($user))
        ->getJson('/api/orders/01HZ0000000000000000000000/timeline')
        ->assertNotFound();
});

it('rejeita timeline sem autenticação', function () {
    $this->getJson('/api/orders/01HZ0000000000000000000000/timeline')
        ->assertUnauthorized();
});
