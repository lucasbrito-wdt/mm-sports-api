<?php

namespace App\Domains\BlogComplete\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;

class TagRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:50'],
            'slug' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
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
