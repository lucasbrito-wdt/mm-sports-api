<?php

namespace App\Domains\Commerce\Requests;

use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderAdminRequest extends BaseFormRequest
{
    public function update(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'correios_tracking_code' => ['nullable', 'string', 'max:64'],
        ];
    }
}
