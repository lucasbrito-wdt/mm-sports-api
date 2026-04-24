<?php

namespace App\Domains\Catalog\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;

class SizeChartAdminRequest extends BaseFormRequest
{
    public function store(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'table_json' => ['required', 'array'],
        ];
    }

    public function update(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'table_json' => ['sometimes', 'array'],
        ];
    }
}
