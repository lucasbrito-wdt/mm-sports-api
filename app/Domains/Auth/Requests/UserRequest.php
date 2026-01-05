<?php

namespace App\Domains\Auth\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\Password;

class UserRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'foto' => ['nullable'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function view(): array
    {
        return [];
    }

    public function store(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role' => ['required', 'exists:\Domains\ACL\Models\Role,slug'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function update(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $this->request->get('id')],
            'role' => ['required', 'exists:\Domains\ACL\Models\Role,slug'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    public function destroy(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'role' => 'cargo',
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'O cargo é obrigatório',
            'role.exists' => 'O cargo selecionado é inválido',
        ];
    }
}
