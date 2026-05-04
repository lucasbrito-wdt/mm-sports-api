<?php

use App\Domains\Marketing\Models\Coupon;
use App\Domains\Marketing\Services\CouponService;
use App\Domains\Marketing\Services\CouponValidationException;

it('valida cupom percentual e calcula desconto sobre o subtotal', function () {
    Coupon::factory()->percentage(10)->create(['code' => 'PCT10']);

    $result = app(CouponService::class)->validate('PCT10', 10000);

    expect($result['discount_cents'])->toBe(1000)
        ->and($result['coupon']->code)->toBe('PCT10');
});

it('valida cupom de valor fixo e respeita o subtotal como teto', function () {
    Coupon::factory()->fixed(50)->create(['code' => 'FIX50']);

    $resultBig = app(CouponService::class)->validate('FIX50', 10000);
    $resultSmall = app(CouponService::class)->validate('FIX50', 3000);

    expect($resultBig['discount_cents'])->toBe(5000)
        ->and($resultSmall['discount_cents'])->toBe(3000);
});

it('aplica max_discount como teto em cupom percentual', function () {
    Coupon::factory()->percentage(50)->create([
        'code' => 'CAP',
        'max_discount' => 20,
    ]);

    $result = app(CouponService::class)->validate('CAP', 10000);

    expect($result['discount_cents'])->toBe(2000);
});

it('rejeita cupom inexistente', function () {
    expect(fn () => app(CouponService::class)->validate('GHOST', 10000))
        ->toThrow(CouponValidationException::class);
});

it('rejeita cupom inativo', function () {
    Coupon::factory()->inactive()->create(['code' => 'OFF']);

    try {
        app(CouponService::class)->validate('OFF', 10000);
        expect(false)->toBeTrue('expected exception');
    } catch (CouponValidationException $e) {
        expect($e->reason)->toBe('inactive');
    }
});

it('rejeita cupom expirado', function () {
    Coupon::factory()->expired()->create(['code' => 'OLD']);

    try {
        app(CouponService::class)->validate('OLD', 10000);
        expect(false)->toBeTrue('expected exception');
    } catch (CouponValidationException $e) {
        expect($e->reason)->toBe('expired');
    }
});

it('rejeita cupom abaixo do subtotal mínimo', function () {
    Coupon::factory()->fixed(20)->create([
        'code' => 'MIN100',
        'min_subtotal' => 100,
    ]);

    try {
        app(CouponService::class)->validate('MIN100', 5000);
        expect(false)->toBeTrue('expected exception');
    } catch (CouponValidationException $e) {
        expect($e->reason)->toBe('min_subtotal');
    }
});

it('rejeita cupom esgotado', function () {
    Coupon::factory()->fixed(10)->create([
        'code' => 'GONE',
        'usage_limit' => 1,
        'usage_count' => 1,
    ]);

    try {
        app(CouponService::class)->validate('GONE', 10000);
        expect(false)->toBeTrue('expected exception');
    } catch (CouponValidationException $e) {
        expect($e->reason)->toBe('usage_exhausted');
    }
});

it('consume incrementa usage_count atomicamente', function () {
    $coupon = Coupon::factory()->fixed(10)->create([
        'code' => 'CONS',
        'usage_limit' => 2,
    ]);

    app(CouponService::class)->consume($coupon);
    app(CouponService::class)->consume($coupon->fresh());

    expect($coupon->fresh()->usage_count)->toBe(2);
});

it('consume rejeita quando o limite foi atingido', function () {
    $coupon = Coupon::factory()->fixed(10)->create([
        'code' => 'FULL',
        'usage_limit' => 1,
        'usage_count' => 1,
    ]);

    expect(fn () => app(CouponService::class)->consume($coupon))
        ->toThrow(CouponValidationException::class);
});

it('normaliza o code para uppercase ao salvar', function () {
    $coupon = Coupon::factory()->create(['code' => 'lower10']);

    expect($coupon->fresh()->code)->toBe('LOWER10');
});
