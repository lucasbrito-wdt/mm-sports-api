<?php

namespace App\Domains\Catalog\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;

class CatalogListRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function view(): array
    {
        return $this->base();
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
