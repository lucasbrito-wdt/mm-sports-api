<?php

namespace App\Domains\Marketing\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;

class CouponValidateRequest extends BaseFormRequest
{
    public function store(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'subtotal_cents' => ['required', 'integer', 'min:1'],
        ];
    }
}
