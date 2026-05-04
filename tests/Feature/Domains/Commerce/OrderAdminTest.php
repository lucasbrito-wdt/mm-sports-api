<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->user = User::factory()->create();
    $this->user->assignRole('user');
});

it('exige autenticação em admin orders', function () {
    $this->getJson('/api/admin/orders')->assertUnauthorized();
});

it('rejeita utilizador sem permissão de orders', function () {
    $this->withHeaders(jwtHeaders($this->user))
        ->getJson('/api/admin/orders')
        ->assertForbidden();
});

it('admin lista encomendas', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [, $v] = CommerceFixtures::publishedProductWithVariant();
    $orderRes = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/orders', [
            'items' => [
                ['product_variant_id' => (string) $v->id, 'quantity' => 1],
            ],
            'address' => ['postal_code' => '01310100', 'street' => 'Av. Paulista', 'number' => '1000', 'district' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP'], 'billing_type' => 'PIX',
        ]);
    $orderRes->assertCreated();
    $orderId = $orderRes->json('order_id');

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/orders?per_page=20');
    $res->assertOk();
    $ids = collect($res->json('data'))->pluck('id')->map(fn ($id) => (string) $id)->all();
    expect($ids)->toContain((string) $orderId);
});

it('admin vê detalhe da encomenda', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [, $v] = CommerceFixtures::publishedProductWithVariant();
    $orderId = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/orders', [
            'items' => [
                ['product_variant_id' => (string) $v->id, 'quantity' => 1],
            ],
            'address' => ['postal_code' => '01310100', 'street' => 'Av. Paulista', 'number' => '1000', 'district' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP'], 'billing_type' => 'PIX',
        ])->json('order_id');

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson("/api/admin/orders/{$orderId}");
    $res->assertOk();
    $res->assertJsonPath('id', (string) $orderId);
    $res->assertJsonStructure(['id', 'status', 'subtotal', 'grand_total', 'user', 'items']);
});
