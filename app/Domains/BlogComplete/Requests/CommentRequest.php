<?php

namespace App\Domains\BlogComplete\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;

class CommentRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'post_id' => ['nullable', 'integer'],
            'author_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'max:100'],
            'comment_text' => ['nullable', 'string'],
            'approved' => ['nullable', 'boolean'],
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
