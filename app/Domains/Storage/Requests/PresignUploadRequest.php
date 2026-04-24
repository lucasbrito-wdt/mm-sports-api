<?php

namespace App\Domains\Storage\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class PresignUploadRequest extends BaseFormRequest
{
    private const MIME_EXT_MAP = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ];

    public function base(): array
    {
        return [
            'mime' => ['required', 'string', Rule::in(array_keys(self::MIME_EXT_MAP))],
            'size' => ['required', 'integer', 'min:1', 'max:10485760'],
            'ext'  => ['required', 'string', Rule::in(['jpg', 'jpeg', 'png', 'webp'])],
        ];
    }

    public function store(): array  { return []; }
    public function update(): array { return []; }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $mime = $this->input('mime');
            $ext  = $this->input('ext');
            if ($mime && $ext && ! in_array($ext, self::MIME_EXT_MAP[$mime] ?? [], true)) {
                $v->errors()->add('ext', 'The extension does not match the declared MIME type.');
            }
        });
    }
}
