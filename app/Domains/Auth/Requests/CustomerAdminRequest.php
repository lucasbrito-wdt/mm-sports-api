<?php

namespace App\Domains\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255', 'min:2'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'document' => ['sometimes', 'nullable', 'string', 'max:18'],
        ];
    }
}
