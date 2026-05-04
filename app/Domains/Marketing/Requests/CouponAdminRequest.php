<?php

namespace App\Domains\Marketing\Requests;

use App\Domains\Marketing\Enums\CouponType;
use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CouponAdminRequest extends BaseFormRequest
{
    public function store(): array
    {
        return $this->fieldRules(allOptional: false);
    }

    public function update(): array
    {
        return $this->fieldRules(allOptional: true);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $start = $this->input('starts_at');
            $end = $this->input('expires_at');
            if ($start && $end && strtotime((string) $end) < strtotime((string) $start)) {
                $v->errors()->add('expires_at', 'expires_at deve ser posterior a starts_at.');
            }
        });
    }

    private function fieldRules(bool $allOptional): array
    {
        $req = $allOptional ? 'sometimes' : 'required';
        $couponId = $this->route('coupon');
        $codeUnique = Rule::unique('coupons', 'code')->ignore($couponId);

        return [
            'code' => [$req, 'string', 'max:64', $codeUnique],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => [$req, Rule::enum(CouponType::class)],
            'value' => [$req, 'numeric', 'min:0'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
