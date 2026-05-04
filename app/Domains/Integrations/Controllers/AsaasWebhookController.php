<?php

namespace App\Domains\Integrations\Controllers;

use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Models\Order;
use App\Domains\Commerce\Services\OrderService;
use App\Domains\Shared\Controller\BaseController;
use App\Domains\Tracking\Services\AnalyticsService;
use App\Domains\Tracking\Services\OrderStatusTracker;
use App\Domains\Tracking\Services\WebhookInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AsaasWebhookController extends BaseController
{
    public function __construct(
        private readonly WebhookInboxService $webhookInboxService,
        private readonly OrderStatusTracker $orderStatusTracker,
        private readonly AnalyticsService $analyticsService,
        private readonly OrderService $orderService,
    ) {
        parent::__construct();
    }

    public function receive(Request $request): JsonResponse
    {
        $payload = $request->all();
        $externalEventId = (string) (data_get($payload, 'payment.id') ?? data_get($payload, 'id') ?? '');
        if ($externalEventId === '') {
            return response()->json(['message' => 'missing event id'], 400);
        }

        $this->webhookInboxService->claimOrIgnore('asaas', $externalEventId, function ($inbox) use ($request, $payload, $externalEventId) {
            $expected = config('services.asaas.webhook_token');
            if (! empty($expected) && $request->header('X-Asaas-Token') !== $expected) {
                throw new RuntimeException('Invalid webhook token');
            }

            $orderId = (string) (data_get($payload, 'payment.externalReference') ?? '');
            if ($orderId === '') {
                throw new RuntimeException('Missing order reference');
            }
            $order = Order::query()->find($orderId);
            if (! $order) {
                throw new RuntimeException('Order not found');
            }

            $inbox->update(['order_id' => $order->id]);

            $status = (string) data_get($payload, 'payment.status', '');
            if (in_array($status, ['RECEIVED', 'CONFIRMED', 'PAYMENT_RECEIVED'], true)) {
                $order->refresh();
                if ($order->status === OrderStatus::Paid) {
                    return;
                }
                $from = $order->status->value;
                $this->orderStatusTracker->record(
                    $order,
                    $from,
                    OrderStatus::Paid->value,
                    'asaas_webhook',
                    ['event_id' => $externalEventId]
                );
                $order->refresh();
                $order->forceFill(['paid_at' => now()])->save();

                $this->analyticsService->track(
                    'payment_confirmed',
                    $order->user_id,
                    ['order_id' => (string) $order->id],
                    'api',
                    request()
                );

                $this->orderService->dispatchOrderPaidEmail($order);
            }
        });

        return response()->json(['ok' => true]);
    }
}
