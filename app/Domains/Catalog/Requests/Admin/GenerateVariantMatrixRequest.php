<?php

namespace App\Domains\Catalog\Requests\Admin;

use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class GenerateVariantMatrixRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [];
    }

    public function store(): array
    {
        return [
            'axes' => ['required', 'array', 'min:1'],
            'axes.*.attribute_id' => ['required', 'string', 'ulid', Rule::exists('attributes', 'id')],
            'axes.*.value_ids' => ['required', 'array', 'min:1'],
            'axes.*.value_ids.*' => ['required', 'string', 'ulid', Rule::exists('attribute_values', 'id')],
        ];
    }

    public function update(): array
    {
        return $this->store();
    }
}
