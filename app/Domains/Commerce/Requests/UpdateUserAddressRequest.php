<?php

namespace App\Domains\Commerce\Requests;

use App\Domains\Commerce\Support\BrazilStates;
use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateUserAddressRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('postal_code')) {
            $this->merge(['postal_code' => preg_replace('/\D/', '', (string) $this->postal_code)]);
        }
        if ($this->has('state') && is_string($this->state)) {
            $this->merge(['state' => strtoupper($this->state)]);
        }
    }

    public function update(): array
    {
        return [
            'recipient_name' => ['sometimes', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'string', 'regex:/^\d{8}$/'],
            'street' => ['sometimes', 'string', 'max:255'],
            'number' => ['sometimes', 'string', 'max:32'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['sometimes', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:255'],
            'state' => ['sometimes', 'string', 'size:2', Rule::in(BrazilStates::codes())],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
