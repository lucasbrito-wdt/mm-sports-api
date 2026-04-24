<?php

namespace App\Domains\Reviews\Requests;

use App\Domains\Reviews\Enums\ReviewModerationStatus;
use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ModerateProductReviewRequest extends BaseFormRequest
{
    public function update(): array
    {
        return [
            'moderation_status' => ['required', Rule::enum(ReviewModerationStatus::class)],
            'store_reply' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
