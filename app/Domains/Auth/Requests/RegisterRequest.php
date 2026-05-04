<?php

namespace App\Domains\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'sometimes|nullable|string|max:20',
            'cpf' => 'sometimes|nullable|string|min:11|max:14',
            'rg' => 'sometimes|nullable|string|max:20',
            'gender' => 'sometimes|nullable|in:m,f,o',
            'birthdate' => 'sometimes|nullable|date|before_or_equal:today',
            'favorite_team' => 'sometimes|nullable|string|max:64',
            'terms' => 'required|boolean',
        ];
    }

    /**
     * Strip non-digits from CPF/phone before validation.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->filled('cpf')) {
            $merge['cpf'] = preg_replace('/\D/', '', (string) $this->input('cpf'));
        }
        if ($this->filled('phone')) {
            $merge['phone'] = preg_replace('/\D/', '', (string) $this->input('phone'));
        }
        if ($this->filled('rg')) {
            $merge['rg'] = preg_replace('/[^\dXx]/', '', (string) $this->input('rg'));
        }
        if ($merge) {
            $this->merge($merge);
        }
    }
}
