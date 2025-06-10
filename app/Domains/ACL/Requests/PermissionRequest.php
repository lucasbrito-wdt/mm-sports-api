<?php

namespace App\Domains\ACL\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;

class PermissionRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'name' => 'required',
            'actions' => 'array',
        ];
    }

    public function view(): array
    {
        return [];
    }

    public function store(): array
    {
        return [
            'actions.*' => 'required|string|unique:permissions,slug',
        ];
    }

    public function update(): array
    {
        return [
            'actions.*' => 'string',
        ];
    }

    public function destroy(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [
            'actions.*.required' => "A action é obrigatória",
            'actions.*.unique' => "A action já existe",
        ];
    }
}
