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

class SendToMetaCapi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [30, 120, 600, 1800, 3600];

    private const EVENT_MAP = [
        'view_item'        => 'ViewContent',
        'add_to_cart'      => 'AddToCart',
        'begin_checkout'   => 'InitiateCheckout',
        'add_payment_info' => 'AddPaymentInfo',
        'purchase'         => 'Purchase',
        'sign_up'          => 'CompleteRegistration',
    ];

    public function __construct(public int $eventId) {}

    public function handle(): void
    {
        $event = TrackingEvent::find($this->eventId);
        if (!$event) return;

        $pixelId     = config('services.meta.pixel_id');
        $accessToken = config('services.meta.access_token');
        $testCode    = config('services.meta.test_event_code');

        if (!$pixelId || !$accessToken) return;

        $metaEventName = self::EVENT_MAP[$event->event_name] ?? null;
        if (!$metaEventName) return;

        $userData = array_filter([
            'client_ip_address' => $event->ip_address,
            'client_user_agent' => $event->user_agent,
            'fbc'               => $event->properties['fbc'] ?? null,
            'fbp'               => $event->properties['fbp'] ?? null,
            'external_id'       => $event->user_id
                ? hash('sha256', (string) $event->user_id)
                : null,
            'em' => isset($event->properties['email'])
                ? hash('sha256', strtolower(trim($event->properties['email'])))
                : null,
            'ph' => isset($event->properties['phone'])
                ? hash('sha256', preg_replace('/\D/', '', $event->properties['phone']))
                : null,
        ]);

        $customData = [];
        if (in_array($event->event_name, ['purchase', 'refund'], true)) {
            $customData = [
                'value'        => $event->revenue_cents / 100,
                'currency'     => $event->currency ?? 'BRL',
                'order_id'     => (string) $event->order_id,
                'content_ids'  => array_column($event->properties['items'] ?? [], 'item_id'),
                'content_type' => 'product',
            ];
        }

        $payload = [
            'data' => [[
                'event_name'       => $metaEventName,
                'event_time'       => $event->event_timestamp->timestamp,
                'event_id'         => (string) $event->id,
                'action_source'    => 'website',
                'event_source_url' => $event->landing_page,
                'user_data'        => $userData,
                'custom_data'      => $customData,
            ]],
        ];

        if ($testCode) {
            $payload['test_event_code'] = $testCode;
        }

        $response = Http::timeout(5)->post(
            "https://graph.facebook.com/v21.0/{$pixelId}/events?access_token={$accessToken}",
            $payload
        );

        if (!$response->successful()) {
            Log::error('Meta CAPI falhou', [
                'event_id' => $this->eventId,
                'status'   => $response->status(),
            ]);
            $this->fail(new \Exception("Meta CAPI HTTP {$response->status()}"));
        }
    }
}
