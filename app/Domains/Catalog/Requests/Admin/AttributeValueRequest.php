<?php

namespace App\Domains\Catalog\Requests\Admin;

use App\Domains\Shared\Requests\BaseFormRequest;

class AttributeValueRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'value' => ['required', 'string', 'max:80'],
            'metadata' => ['nullable', 'array'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function store(): array
    {
        return $this->base();
    }

    public function update(): array
    {
        return $this->base();
    }
}
