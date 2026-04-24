<?php

namespace App\Domains\Tracking\Services;

use App\Domains\Tracking\Models\AnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AnalyticsService
{
    public function __construct(
        private readonly AnalyticsEvent $analyticsEvent,
    ) {}

    public function track(
        string $name,
        ?string $userId,
        array $properties = [],
        string $source = 'api',
        ?Request $request = null,
    ): void {
        $allowed = config('analytics.allowed_event_names', []);
        if (! in_array($name, $allowed, true)) {
            throw new InvalidArgumentException("Unknown analytics event: {$name}");
        }

        $this->analyticsEvent->newQuery()->create([
            'id' => (string) Str::ulid(),
            'user_id' => $userId,
            'name' => $name,
            'properties' => $properties ?: null,
            'source' => $source,
            'request_id' => $request?->header('X-Request-Id'),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
