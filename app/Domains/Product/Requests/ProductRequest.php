<?php

namespace App\Domains\Product\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;

class ProductRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'nome' => ['nullable', 'string', 'max:150'],
            'descricao' => ['nullable'],
            'preco' => ['required', 'numeric'],
            'preco_promocional' => ['nullable', 'numeric'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'categoria_id' => ['nullable'],
            'ativo' => ['nullable', 'boolean'],
            'estoque' => ['nullable', 'integer'],
            'peso' => ['nullable', 'numeric'],
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
