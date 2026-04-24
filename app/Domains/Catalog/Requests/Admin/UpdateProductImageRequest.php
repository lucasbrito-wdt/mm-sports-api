<?php

namespace App\Domains\Catalog\Requests\Admin;

use App\Domains\Shared\Requests\BaseFormRequest;

class UpdateProductImageRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'alt'                => ['nullable', 'string', 'max:255'],
            'attribute_value_id' => ['nullable', 'integer', 'exists:attribute_values,id'],
            'display_order'      => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function store(): array  { return []; }
    public function update(): array { return []; }
}
