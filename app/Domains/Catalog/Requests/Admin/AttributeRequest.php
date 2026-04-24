<?php

namespace App\Domains\Catalog\Requests\Admin;

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class AttributeRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'label' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::enum(AttributeType::class)],
            'input_type' => ['required', Rule::enum(AttributeInputType::class)],
            'is_filterable' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function store(): array
    {
        return [
            ...$this->base(),
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z_]+$/', 'unique:attributes,code'],
        ];
    }

    public function update(): array
    {
        return $this->base();
    }
}
