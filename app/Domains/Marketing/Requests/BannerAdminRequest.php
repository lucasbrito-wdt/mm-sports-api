<?php

namespace App\Domains\Marketing\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class BannerAdminRequest extends BaseFormRequest
{
    public function store(): array
    {
        return [
            'internal_title' => ['required', 'string', 'max:255'],
            'image_url' => ['required', 'string', 'max:2048'],
            'destination_url' => ['required', 'string', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'device' => ['nullable', 'string', Rule::in(['all', 'desktop', 'mobile'])],
        ];
    }

    public function update(): array
    {
        return [
            'internal_title' => ['sometimes', 'string', 'max:255'],
            'image_url' => ['sometimes', 'string', 'max:2048'],
            'destination_url' => ['sometimes', 'string', 'max:2048'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'device' => ['nullable', 'string', Rule::in(['all', 'desktop', 'mobile'])],
        ];
    }
}
