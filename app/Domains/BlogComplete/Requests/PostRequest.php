<?php

namespace App\Domains\BlogComplete\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;

class PostRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:200'],
            'content' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'excerpt' => ['nullable', 'string', 'max:500'],
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
