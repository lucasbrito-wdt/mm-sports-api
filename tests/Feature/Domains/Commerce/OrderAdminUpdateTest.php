<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Models\Order;
use App\Domains\Tracking\Models\AnalyticsEvent;
use App\Domains\Tracking\Models\AuditLog;
use App\Domains\Tracking\Models\OrderStatusTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('admin cancela encomenda pendente e gera transição e audit', function () {
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
        ->patchJson("/api/admin/orders/{$orderId}", [
            'status' => 'cancelled',
        ]);
    $res->assertOk();
    $order = Order::query()->findOrFail($orderId);
    expect($order->status)->toBe(OrderStatus::Cancelled);
    expect(
        OrderStatusTransition::query()
            ->where('order_id', $orderId)
            ->where('to_status', 'cancelled')
            ->where('source', 'admin')
            ->exists()
    )->toBeTrue();
    expect(
        AuditLog::query()
            ->where('auditable_id', (string) $orderId)
            ->where('action', 'orders.update')
            ->exists()
    )->toBeTrue();
});

it('admin define tracking e transição para shipped com evento de analytics', function () {
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

    $order = Order::query()->findOrFail($orderId);
    $order->update([
        'status' => OrderStatus::Paid,
        'paid_at' => now(),
    ]);
    $this->withHeaders(jwtHeaders($this->admin))
        ->patchJson("/api/admin/orders/{$orderId}", [
            'status' => 'shipped',
            'correios_tracking_code' => 'AA123456789BR',
        ])
        ->assertOk();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Shipped);
    expect($order->correios_tracking_code)->toBe('AA123456789BR');
    expect($order->shipped_at)->not->toBeNull();
    expect(
        AnalyticsEvent::query()
            ->where('name', 'order_shipped')
            ->where('properties->order_id', (string) $orderId)
            ->exists()
    )->toBeTrue();
});
