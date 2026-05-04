<?php

namespace App\Domains\Marketing\Controllers;

use App\Domains\Marketing\Requests\CouponValidateRequest;
use App\Domains\Marketing\Services\CouponService;
use App\Domains\Marketing\Services\CouponValidationException;
use Illuminate\Http\JsonResponse;

class CouponController
{
    public function __construct(
        private readonly CouponService $service,
    ) {}

    public function validate(CouponValidateRequest $request): JsonResponse
    {
        $data = $request->validated();
        try {
            $result = $this->service->validate($data['code'], (int) $data['subtotal_cents']);
        } catch (CouponValidationException $e) {
            return response()->json([
                'valid' => false,
                'reason' => $e->reason,
                'message' => $e->getMessage(),
            ], 422);
        }

        $coupon = $result['coupon'];

        return response()->json([
            'valid' => true,
            'discount_cents' => $result['discount_cents'],
            'coupon' => [
                'code' => $coupon->code,
                'description' => $coupon->description,
                'type' => $coupon->type->value,
                'value' => (string) $coupon->value,
            ],
        ]);
    }
}
