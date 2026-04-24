<?php

namespace App\Domains\Storage\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class PresignUploadRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'mime' => ['required', 'string', Rule::in(['image/jpeg', 'image/png', 'image/webp'])],
            'size' => ['required', 'integer', 'min:1', 'max:10485760'],  // 10 MB
            'ext'  => ['required', 'string', Rule::in(['jpg', 'jpeg', 'png', 'webp'])],
        ];
    }

    public function store(): array { return $this->base(); }
    public function update(): array { return $this->base(); }
}
