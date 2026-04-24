<?php

namespace App\Domains\Commerce\Requests;

use App\Domains\Commerce\Models\UserAddress;
use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CheckoutQuoteRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('destination_postal_code')) {
            $this->merge([
                'destination_postal_code' => preg_replace('/\D/', '', (string) $this->destination_postal_code),
            ]);
        }
        if ($this->filled('user_address_id') && $this->user() && ! $this->filled('destination_postal_code')) {
            $row = UserAddress::query()
                ->where('user_id', $this->user()->id)
                ->where('id', $this->user_address_id)
                ->first();
            if ($row) {
                $this->merge(['destination_postal_code' => preg_replace('/\D/', '', (string) $row->postal_code)]);
            }
        }
    }

    public function store(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'ulid', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'items.*.personalization' => ['nullable', 'array'],
            'items.*.personalization.*.option_id' => ['required', 'ulid'],
            'items.*.personalization.*.value' => ['required'],
            'destination_postal_code' => ['required', 'string', 'size:8'],
            'user_address_id' => [
                'nullable',
                'ulid',
                Rule::exists('user_addresses', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }
}
