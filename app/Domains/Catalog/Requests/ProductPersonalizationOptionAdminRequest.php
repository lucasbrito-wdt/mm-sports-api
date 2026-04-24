<?php

namespace App\Domains\Catalog\Requests;

use App\Domains\Catalog\Enums\PersonalizationOptionType;
use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ProductPersonalizationOptionAdminRequest extends BaseFormRequest
{
    public function store(): array
    {
        return [
            'type' => ['required', Rule::enum(PersonalizationOptionType::class)],
            'label' => ['required', 'string', 'max:255'],
            'is_required' => ['boolean'],
            'additional_price' => ['required', 'numeric', 'min:0'],
            'max_length' => ['nullable', 'integer', 'min:0'],
            'options_json' => ['nullable', 'array'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    public function update(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(PersonalizationOptionType::class)],
            'label' => ['sometimes', 'string', 'max:255'],
            'is_required' => ['boolean'],
            'additional_price' => ['sometimes', 'numeric', 'min:0'],
            'max_length' => ['nullable', 'integer', 'min:0'],
            'options_json' => ['nullable', 'array'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
