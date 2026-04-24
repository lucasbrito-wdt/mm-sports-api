<?php

namespace App\Domains\Commerce\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Models\Order;
use App\Domains\Commerce\Models\OrderItem;
use App\Domains\Commerce\Models\UserAddress;
use App\Domains\Integrations\Services\AsaasService;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Shared\Utils\IntHelper;
use App\Domains\Tracking\Services\AnalyticsService;
use App\Domains\Tracking\Services\OrderStatusTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OrderService extends BaseService
{
    public function __construct(
        private readonly Order $order,
        private readonly OrderItem $orderItem,
        private readonly CheckoutQuoteService $checkoutQuoteService,
        private readonly OrderStatusTracker $orderStatusTracker,
        private readonly AsaasService $asaasService,
        private readonly AnalyticsService $analyticsService,
    ) {
        $this->setModel($this->order);
    }

    public function createFromUser(User $user, array $data): Order
    {
        $quote = $this->checkoutQuoteService->computeQuote($user->id, [
            'items' => $data['items'],
            'destination_postal_code' => $data['destination_postal_code'],
            'user_address_id' => $data['user_address_id'] ?? null,
        ]);

        $addressSnapshot = $this->resolveShippingSnapshot($user->id, $data);

        return DB::transaction(function () use ($user, $quote, $addressSnapshot) {
            $orderId = (string) Str::ulid();
            $cartHash = hash('sha256', json_encode($quote['lines']) ?: '');

            $order = $this->order->newQuery()->create([
                'id' => $orderId,
                'user_id' => $user->id,
                'status' => OrderStatus::PendingPayment,
                'subtotal' => $quote['subtotal'],
                'discount_total' => $quote['discount_total'],
                'shipping_total' => $quote['shipping_total'],
                'grand_total' => $quote['grand_total'],
                'shipping_service_code' => $quote['shipping']['service_code'] ?? null,
                'shipping_quote_json' => $quote['shipping'],
                'shipping_address_snapshot' => $addressSnapshot,
            ]);

            $this->orderStatusTracker->record(
                $order,
                null,
                OrderStatus::PendingPayment->value,
                'system',
                ['cart_summary_hash' => $cartHash]
            );

            foreach ($quote['lines'] as $line) {
                $this->orderItem->newQuery()->create([
                    'id' => (string) Str::ulid(),
                    'order_id' => $order->id,
                    'product_variant_id' => $line['product_variant_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => 0,
                    'product_title_snapshot' => $line['product_title'],
                    'variant_label_snapshot' => $line['variant_label'],
                    'personalization_snapshot' => $line['personalization_snapshot'] ?? null,
                ]);
            }

            $pay = $this->asaasService->createPayment($order, (float) $quote['grand_total']);
            $order->update([
                'asaas_payment_id' => $pay['asaas_payment_id'],
                'asaas_customer_id' => $pay['asaas_customer_id'],
            ]);

            $this->analyticsService->track(
                'order_created',
                $user->id,
                ['order_id' => (string) $order->id],
                'api',
                request()
            );

            return $order->fresh();
        });
    }

    /**
     * Encomendas do utilizador (paginado; não inclui encomendas de outros, exceto se for admin a consultar o detalhe noutro método).
     */
    public function listForUser(User $user, array $options = []): array
    {
        $perPage = IntHelper::tryParser($options['per_page'] ?? 15) ?? 15;
        $perPage = min(max($perPage, 1), 100);

        $pag = $this->order->newQuery()
            ->where('user_id', $user->id)
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return [
            'data' => $pag->getCollection()
                ->map(fn (Order $o) => [
                    'id' => (string) $o->id,
                    'status' => $o->status->value,
                    'grand_total' => (string) $o->grand_total,
                    'items_count' => $o->items_count,
                    'created_at' => $o->created_at?->toIso8601String(),
                ])->values()->all(),
            'total' => $pag->total(),
            'page' => $pag->currentPage(),
            'current_page' => $pag->currentPage(),
            'last_page' => $pag->lastPage(),
        ];
    }

    /**
     * Detalhe da encomenda: dono ou administrador. Inclui linhas, snapshots e rastreio.
     */
    public function detailForUser(User $user, string $orderId): array
    {
        $order = $this->order->newQuery()
            ->whereKey($orderId)
            ->with(['items.productVariant'])
            ->firstOrFail();

        if (! $this->isAdmin($user) && (string) $order->user_id !== (string) $user->id) {
            throw new InvalidArgumentException('Forbidden');
        }

        return $this->serializeOrderDetail($order);
    }

    public function timelineForUser(User $user, string $orderId): array
    {
        $order = $this->order->newQuery()->where('id', $orderId)->firstOrFail();
        if (! $this->isAdmin($user) && (string) $order->user_id !== (string) $user->id) {
            throw new InvalidArgumentException('Forbidden');
        }

        $transitions = $order->statusTransitions()->orderBy('created_at')->get();

        return [
            'order_id' => (string) $order->id,
            'correios_tracking_code' => $order->correios_tracking_code,
            'transitions' => $transitions->map(fn ($t) => [
                'from_status' => $t->from_status,
                'to_status' => $t->to_status,
                'source' => $t->source,
                'meta' => $t->meta,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    private function resolveShippingSnapshot(string $userId, array $data): array
    {
        if (! empty($data['user_address_id'])) {
            $a = UserAddress::query()
                ->where('user_id', $userId)
                ->where('id', $data['user_address_id'])
                ->firstOrFail();

            return [
                'recipient_name' => $a->recipient_name,
                'postal_code' => $a->postal_code,
                'street' => $a->street,
                'number' => $a->number,
                'complement' => $a->complement,
                'district' => $a->district,
                'city' => $a->city,
                'state' => $a->state,
            ];
        }

        return [
            'postal_code' => $data['destination_postal_code'],
            'note' => 'quote_only_address',
        ];
    }

    private function serializeOrderDetail(Order $order): array
    {
        return [
            'id' => (string) $order->id,
            'status' => $order->status->value,
            'subtotal' => (string) $order->subtotal,
            'discount_total' => (string) $order->discount_total,
            'shipping_total' => (string) $order->shipping_total,
            'grand_total' => (string) $order->grand_total,
            'correios_tracking_code' => $order->correios_tracking_code,
            'asaas_payment_id' => $order->asaas_payment_id,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'shipped_at' => $order->shipped_at?->toIso8601String(),
            'shipping' => $order->shipping_quote_json,
            'shipping_address_snapshot' => $order->shipping_address_snapshot,
            'created_at' => $order->created_at?->toIso8601String(),
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => (string) $item->id,
                    'product_variant_id' => (string) $item->product_variant_id,
                    'sku' => $item->productVariant?->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'discount_amount' => (string) $item->discount_amount,
                    'product_title' => $item->product_title_snapshot,
                    'variant_label' => $item->variant_label_snapshot,
                    'personalization_snapshot' => $item->personalization_snapshot,
                ];
            })->all(),
        ];
    }

    private function isAdmin(User $user): bool
    {
        try {
            return $user->roles()->where('slug', 'admin')->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
