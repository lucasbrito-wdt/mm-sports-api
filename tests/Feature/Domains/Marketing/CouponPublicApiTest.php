<?php

use App\Domains\Marketing\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('valida cupom existente e retorna desconto em centavos', function () {
    Coupon::factory()->percentage(15)->create(['code' => 'WELCOME15']);

    $res = $this->postJson('/api/coupons/validate', [
        'code' => 'welcome15',
        'subtotal_cents' => 20000,
    ]);

    $res->assertOk();
    expect($res->json('valid'))->toBeTrue()
        ->and($res->json('discount_cents'))->toBe(3000)
        ->and($res->json('coupon.code'))->toBe('WELCOME15')
        ->and($res->json('coupon.type'))->toBe('percentage');
});

it('retorna 422 com reason quando o cupom nao existe', function () {
    $res = $this->postJson('/api/coupons/validate', [
        'code' => 'GHOST',
        'subtotal_cents' => 5000,
    ]);

    $res->assertStatus(422);
    expect($res->json('valid'))->toBeFalse()
        ->and($res->json('reason'))->toBe('not_found');
});

it('retorna 422 quando o subtotal esta abaixo do minimo', function () {
    Coupon::factory()->fixed(20)->create([
        'code' => 'MIN50',
        'min_subtotal' => 50,
    ]);

    $res = $this->postJson('/api/coupons/validate', [
        'code' => 'MIN50',
        'subtotal_cents' => 1000,
    ]);

    $res->assertStatus(422);
    expect($res->json('reason'))->toBe('min_subtotal');
});

it('rejeita request sem code', function () {
    $res = $this->postJson('/api/coupons/validate', [
        'subtotal_cents' => 1000,
    ]);

    $res->assertStatus(422);
});
