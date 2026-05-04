<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Models\Coupon;
use Illuminate\Support\Facades\DB;

class CouponService
{
    /**
     * Validate a coupon code against a subtotal (in cents) and return a result.
     *
     * @return array{coupon: Coupon, discount_cents: int}
     *
     * @throws CouponValidationException
     */
    public function validate(string $code, int $subtotalCents): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            throw new CouponValidationException('Código de cupom obrigatório.', 'invalid_code');
        }

        /** @var Coupon|null $coupon */
        $coupon = Coupon::query()->where('code', $code)->first();
        if ($coupon === null) {
            throw new CouponValidationException('Cupom não encontrado.', 'not_found');
        }

        if (! $coupon->active) {
            throw new CouponValidationException('Cupom inativo.', 'inactive');
        }

        if (! $coupon->isWithinWindow()) {
            throw new CouponValidationException('Cupom fora do período de validade.', 'expired');
        }

        if (! $coupon->hasUsesLeft()) {
            throw new CouponValidationException('Cupom esgotado.', 'usage_exhausted');
        }

        if ($coupon->min_subtotal !== null) {
            $minCents = (int) round((float) $coupon->min_subtotal * 100);
            if ($subtotalCents < $minCents) {
                throw new CouponValidationException(
                    'Subtotal abaixo do mínimo exigido pelo cupom.',
                    'min_subtotal',
                );
            }
        }

        $discount = $coupon->discountForSubtotalCents($subtotalCents);
        if ($discount <= 0) {
            throw new CouponValidationException('Cupom não gera desconto para este pedido.', 'no_discount');
        }

        return [
            'coupon' => $coupon,
            'discount_cents' => $discount,
        ];
    }

    /**
     * Atomically increment usage_count when a coupon is consumed by an order.
     * Re-checks usage limit under row lock to avoid races.
     *
     * @throws CouponValidationException
     */
    public function consume(Coupon $coupon): void
    {
        DB::transaction(function () use ($coupon) {
            /** @var Coupon $locked */
            $locked = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            if (! $locked->hasUsesLeft()) {
                throw new CouponValidationException('Cupom esgotado.', 'usage_exhausted');
            }
            $locked->increment('usage_count');
        });
    }
}
