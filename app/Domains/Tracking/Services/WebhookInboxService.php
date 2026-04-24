<?php

namespace App\Domains\Tracking\Services;

use App\Domains\Tracking\Models\WebhookInbox;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class WebhookInboxService
{
    public function __construct(
        private readonly WebhookInbox $webhookInbox,
    ) {}

    public function claimOrIgnore(string $provider, string $externalEventId, Closure $process): void
    {
        if ($this->webhookInbox->newQuery()
            ->where('provider', $provider)
            ->where('external_event_id', $externalEventId)
            ->exists()
        ) {
            return;
        }

        try {
            DB::transaction(function () use ($provider, $externalEventId, $process) {
                $row = $this->webhookInbox->newQuery()->create([
                    'id' => (string) Str::ulid(),
                    'provider' => $provider,
                    'external_event_id' => $externalEventId,
                    'processing_result' => 'claimed',
                ]);

                try {
                    $process($row);
                    $row->update(['processing_result' => 'processed', 'error_message' => null]);
                } catch (Throwable $e) {
                    $row->update(['processing_result' => 'failed', 'error_message' => $e->getMessage()]);
                    throw $e;
                }
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateError($e)) {
                return;
            }
            throw $e;
        }
    }

    private function isDuplicateError(QueryException $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'UNIQUE') || str_contains($msg, 'unique')
            || (int) $e->getCode() === 23000;
    }
}
