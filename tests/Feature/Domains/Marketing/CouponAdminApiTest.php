<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Models\Order;
use App\Domains\Marketing\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('admin cria cupom percentual', function () {
    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/coupons', [
            'code' => 'spring25',
            'description' => 'Cupom de primavera',
            'type' => 'percentage',
            'value' => 25,
            'usage_limit' => 100,
            'min_subtotal' => 100,
            'active' => true,
        ]);

    $res->assertCreated();
    expect($res->json('code'))->toBe('SPRING25');
    expect(Coupon::query()->where('code', 'SPRING25')->exists())->toBeTrue();
});

it('admin nao pode criar cupom com codigo duplicado', function () {
    Coupon::factory()->create(['code' => 'DUP']);

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/coupons', [
            'code' => 'DUP',
            'type' => 'fixed',
            'value' => 10,
        ]);

    $res->assertStatus(422);
});

it('admin lista cupons paginados', function () {
    Coupon::factory()->count(3)->create();

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/coupons');

    $res->assertOk();
    expect($res->json('total'))->toBe(3);
});

it('admin atualiza cupom', function () {
    $coupon = Coupon::factory()->fixed(20)->create(['code' => 'OLD']);

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->patchJson("/api/admin/coupons/{$coupon->id}", [
            'value' => 30,
            'active' => false,
        ]);

    $res->assertOk();
    expect((float) $coupon->fresh()->value)->toBe(30.0);
    expect($coupon->fresh()->active)->toBeFalse();
});

it('admin deleta cupom', function () {
    $coupon = Coupon::factory()->create();

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->deleteJson("/api/admin/coupons/{$coupon->id}");

    $res->assertNoContent();
    expect(Coupon::query()->where('id', $coupon->id)->exists())->toBeFalse();
});

it('admin obtem metricas do cupom', function () {
    $coupon = Coupon::factory()->fixed(10)->create([
        'code' => 'METRIC',
        'usage_limit' => 100,
        'usage_count' => 2,
    ]);
    $user = User::factory()->create();
    Order::query()->create([
        'user_id' => $user->id,
        'coupon_id' => $coupon->id,
        'coupon_code' => 'METRIC',
        'status' => OrderStatus::Paid,
        'subtotal' => 100,
        'discount_total' => 10,
        'shipping_total' => 5,
        'grand_total' => 95,
        'shipping_address_snapshot' => ['city' => 'X'],
    ]);
    Order::query()->create([
        'user_id' => $user->id,
        'coupon_id' => $coupon->id,
        'coupon_code' => 'METRIC',
        'status' => OrderStatus::Shipped,
        'subtotal' => 200,
        'discount_total' => 10,
        'shipping_total' => 5,
        'grand_total' => 195,
        'shipping_address_snapshot' => ['city' => 'X'],
    ]);

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson("/api/admin/coupons/{$coupon->id}/metrics");

    $res->assertOk();
    expect($res->json('usage_count'))->toBe(2)
        ->and($res->json('orders_completed'))->toBe(2)
        ->and((float) $res->json('total_discount'))->toBe(20.0)
        ->and((float) $res->json('total_revenue'))->toBe(290.0);
});

it('rejeita usuario nao admin nas rotas administrativas', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $res = $this->withHeaders(jwtHeaders($user))
        ->getJson('/api/admin/coupons');

    $res->assertForbidden();
});
