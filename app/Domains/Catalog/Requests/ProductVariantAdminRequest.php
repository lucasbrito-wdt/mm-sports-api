<?php

namespace App\Domains\Catalog\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ProductVariantAdminRequest extends BaseFormRequest
{
    public function store(): array
    {
        return [
            'sku' => ['required', 'string', 'max:255', 'unique:product_variants,sku'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'length_cm' => ['nullable', 'numeric'],
            'width_cm' => ['nullable', 'numeric'],
            'height_cm' => ['nullable', 'numeric'],
            'attribute_payload' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ];
    }

    public function update(): array
    {
        $id = (string) $this->route('variant');

        return [
            'sku' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('product_variants', 'sku')->ignore($id, 'id'),
            ],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'length_cm' => ['nullable', 'numeric'],
            'width_cm' => ['nullable', 'numeric'],
            'height_cm' => ['nullable', 'numeric'],
            'attribute_payload' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ];
    }
}
