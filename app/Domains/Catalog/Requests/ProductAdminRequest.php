<?php

namespace App\Domains\Catalog\Requests;

use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ProductAdminRequest extends BaseFormRequest
{
    public function store(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'origin' => ['required', Rule::enum(ProductOrigin::class)],
            'allows_personalization' => ['boolean'],
            'size_chart_id' => ['nullable', 'ulid', 'exists:size_charts,id'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'ncm' => ['nullable', 'string', 'max:32'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function update(): array
    {
        $id = (string) $this->route('product');

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($id, 'id')],
            'description' => ['nullable', 'string'],
            'origin' => ['sometimes', Rule::enum(ProductOrigin::class)],
            'allows_personalization' => ['boolean'],
            'size_chart_id' => ['nullable', 'ulid', 'exists:size_charts,id'],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
            'ncm' => ['nullable', 'string', 'max:32'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
