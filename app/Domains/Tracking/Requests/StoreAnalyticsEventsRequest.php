<?php

namespace App\Domains\Tracking\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreAnalyticsEventsRequest extends BaseFormRequest
{
    public function store(): array
    {
        $allowed = config('analytics.allowed_event_names', []);

        return [
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*.name' => ['required', 'string', Rule::in($allowed)],
            'events.*.properties' => ['nullable', 'array'],
            'events.*.client_timestamp' => ['nullable', 'string'],
        ];
    }
}
