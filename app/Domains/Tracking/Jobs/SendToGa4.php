<?php

namespace App\Domains\Tracking\Jobs;

use App\Domains\Tracking\Models\TrackingEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendToGa4 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [30, 120, 600, 1800, 3600];

    public function __construct(public int $eventId) {}

    public function handle(): void
    {
        $event = TrackingEvent::find($this->eventId);
        if (!$event) return;

        $measurementId = config('services.ga4.measurement_id');
        $apiSecret     = config('services.ga4.api_secret');

        if (!$measurementId || !$apiSecret) {
            Log::warning('GA4 não configurado', ['event_id' => $this->eventId]);
            return;
        }

        $payload = [
            'client_id' => $event->anonymous_id ?? (string) $event->session_id,
            'events'    => [[
                'name'   => $event->event_name,
                'params' => $this->buildParams($event),
            ]],
        ];

        if ($event->user_id) {
            $payload['user_id'] = (string) $event->user_id;
        }

        $response = Http::timeout(5)->post(
            "https://www.google-analytics.com/mp/collect?measurement_id={$measurementId}&api_secret={$apiSecret}",
            $payload
        );

        if (!$response->successful()) {
            Log::error('GA4 falhou', [
                'event_id' => $this->eventId,
                'status'   => $response->status(),
            ]);
            $this->fail(new \Exception("GA4 HTTP {$response->status()}"));
        }
    }

    private function buildParams(TrackingEvent $event): array
    {
        $base = array_filter([
            'session_id'           => $event->session_id,
            'engagement_time_msec' => 100,
            'page_location'        => $event->landing_page,
            'page_referrer'        => $event->referrer,
            'source'               => $event->utm_source,
            'medium'               => $event->utm_medium,
            'campaign'             => $event->utm_campaign,
        ]);

        if (in_array($event->event_name, ['purchase', 'refund'], true)) {
            $base['transaction_id'] = (string) $event->order_id;
            $base['value']          = $event->revenue_cents / 100;
            $base['currency']       = $event->currency ?? 'BRL';
            $base['items']          = $event->properties['items'] ?? [];
            if ($event->properties['coupon_code'] ?? false) {
                $base['coupon'] = $event->properties['coupon_code'];
            }
        }

        if (in_array($event->event_name, ['view_item', 'add_to_cart'], true)) {
            $base['currency'] = $event->currency ?? 'BRL';
            $base['value']    = ($event->revenue_cents ?? 0) / 100;
            $base['items']    = $event->properties['items'] ?? [
                ['item_id' => (string) $event->product_id],
            ];
        }

        return $base;
    }
}
