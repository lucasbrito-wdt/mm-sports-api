<?php

namespace App\Domains\Category\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;

class CategoryRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'nome' => ['nullable', 'string', 'max:100'],
            'descricao' => ['nullable'],
            'slug' => ['nullable', 'string', 'max:100'],
            'ativa' => ['nullable', 'boolean'],
            'ordem' => ['nullable', 'integer'],
        ];
    }

    public function view(): array
    {
        return [];
    }

    public function store(): array
    {
        return [];
    }

    public function update(): array
    {
        return [];
    }

    public function destroy(): array
    {
        return [];
    }
}
