<?php

use App\Domains\Auth\Models\User;
use App\Domains\Commerce\Models\Order;
use App\Domains\Marketing\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceFixtures;

uses(RefreshDatabase::class);

it('aplica cupom percentual no pedido e incrementa usage_count', function () {
    $user = User::factory()->create();
    [, $v] = CommerceFixtures::publishedProductWithVariant();
    $coupon = Coupon::factory()->percentage(10)->create([
        'code' => 'PCT10',
        'usage_limit' => 5,
    ]);

    $res = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/orders', [
            'items' => [['product_variant_id' => (string) $v->id, 'quantity' => 1]],
            'address' => ['postal_code' => '01310100', 'street' => 'Av. Paulista', 'number' => '1000', 'district' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP'],
            'billing_type' => 'PIX',
            'coupon_code' => 'pct10',
        ]);

    $res->assertCreated();
    /** @var Order $order */
    $order = Order::query()->findOrFail($res->json('order_id'));
    expect($order->coupon_id)->toBe((string) $coupon->id)
        ->and($order->coupon_code)->toBe('PCT10')
        ->and((float) $order->discount_total)->toBeGreaterThan(0);

    expect($coupon->fresh()->usage_count)->toBe(1);
});

it('rejeita pedido com cupom invalido', function () {
    $user = User::factory()->create();
    [, $v] = CommerceFixtures::publishedProductWithVariant();

    $res = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/orders', [
            'items' => [['product_variant_id' => (string) $v->id, 'quantity' => 1]],
            'address' => ['postal_code' => '01310100', 'street' => 'Av. Paulista', 'number' => '1000', 'district' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP'],
            'billing_type' => 'PIX',
            'coupon_code' => 'GHOST',
        ]);

    $res->assertStatus(422);
    expect(Order::query()->count())->toBe(0);
});
