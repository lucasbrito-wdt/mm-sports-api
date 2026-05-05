<?php

namespace App\Domains\Commerce\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Models\Order;
use App\Domains\Commerce\Models\OrderItem;
use App\Domains\Commerce\Models\UserPaymentMethod;
use App\Domains\Integrations\Services\AsaasService;
use App\Domains\Marketing\Models\Coupon;
use App\Domains\Marketing\Services\CouponService;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Shared\Utils\IntHelper;
use App\Domains\Tracking\Services\AnalyticsService;
use App\Domains\Tracking\Services\OrderStatusTracker;
use App\Mail\OrderPaidMail;
use App\Mail\OrderPlacedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        private readonly CouponService $couponService,
    ) {
        $this->setModel($this->order);
    }

    public function createFromUser(User $user, array $data): Order
    {
        if (empty($data['items'])) {
            throw new InvalidArgumentException('items não pode ser vazio');
        }

        $this->syncUserContact($user, $data['customer'] ?? []);

        $addr = $data['address'];
        $quote = $this->checkoutQuoteService->computeQuote($user->id, [
            'items' => $data['items'],
            'destination_postal_code' => $addr['postal_code'],
        ]);

        $addressSnapshot = [
            'recipient_name' => $user->name,
            'postal_code' => $addr['postal_code'],
            'street' => $addr['street'],
            'number' => $addr['number'],
            'complement' => $addr['complement'] ?? null,
            'district' => $addr['district'] ?? null,
            'city' => $addr['city'],
            'state' => $addr['state'],
        ];

        $billingType = strtoupper($data['billing_type']);

        $couponData = $this->resolveCoupon($data['coupon_code'] ?? null, (float) $quote['subtotal']);

        return DB::transaction(function () use ($user, $data, $quote, $addressSnapshot, $billingType, $couponData) {
            $orderId = (string) Str::ulid();
            $cartHash = hash('sha256', json_encode($quote['lines']) ?: '');

            $couponDiscount = $couponData['discount'] ?? 0.0;
            $discountTotal = round((float) $quote['discount_total'] + $couponDiscount, 2);
            $afterDiscount = round((float) $quote['subtotal'] - $discountTotal, 2);
            $grandTotal = round($afterDiscount + (float) $quote['shipping_total'], 2);

            $order = $this->order->newQuery()->create([
                'id' => $orderId,
                'user_id' => $user->id,
                'coupon_id' => $couponData['coupon_id'] ?? null,
                'coupon_code' => $couponData['code'] ?? null,
                'status' => OrderStatus::PendingPayment,
                'payment_method' => strtolower($billingType),
                'subtotal' => $quote['subtotal'],
                'discount_total' => $discountTotal,
                'shipping_total' => $quote['shipping_total'],
                'grand_total' => $grandTotal,
                'shipping_service_code' => $quote['shipping']['service_code'] ?? null,
                'shipping_quote_json' => $quote['shipping'],
                'shipping_address_snapshot' => $addressSnapshot,
                'notes' => $data['notes'] ?? null,
            ]);

            if (! empty($couponData['model'])) {
                $this->couponService->consume($couponData['model']);
            }

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

            $paymentResult = match ($billingType) {
                'PIX' => $this->handlePixPayment($order),
                'CREDIT_CARD' => $this->handleCardPayment($order, $data),
                'BOLETO' => $this->handleBoletoPayment($order),
                default => throw new InvalidArgumentException('billing_type inválido: '.$billingType),
            };

            $order->update($paymentResult);

            $this->analyticsService->track(
                'order_created',
                $user->id,
                ['order_id' => (string) $order->id, 'billing_type' => $billingType],
                'api',
                request()
            );

            return $order->fresh();
        });
    }

    /**
     * Send the order-placed e-mail to the customer. Failures are swallowed —
     * a broken mailer must never block order creation.
     */
    public function dispatchOrderPlacedEmail(Order $order): void
    {
        $email = $order->user?->email;
        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new OrderPlacedMail($order));
        } catch (\Throwable $e) {
            Log::warning('OrderPlacedMail dispatch failed', [
                'order_id' => (string) $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send the order-paid e-mail to the customer. Failures are swallowed —
     * a broken mailer must never break the webhook flow.
     */
    public function dispatchOrderPaidEmail(Order $order): void
    {
        $email = $order->user?->email;
        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new OrderPaidMail($order));
        } catch (\Throwable $e) {
            Log::warning('OrderPaidMail dispatch failed', [
                'order_id' => (string) $order->id,
                'error' => $e->getMessage(),
            ]);
        }
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
            'asaas_pix_qr_code' => $order->asaas_pix_qr_code,
            'asaas_pix_copy_paste' => $order->asaas_pix_copy_paste,
            'asaas_pix_expires_at' => $order->asaas_pix_expires_at?->toIso8601String(),
            'asaas_boleto_url' => $order->asaas_boleto_url,
            'items' => $order->items->map(function ($item) {
                $variant = $item->productVariant;
                $variant?->loadMissing('product.images');
                $imageUrl = $variant?->product?->images?->first()?->url;

                return [
                    'id' => (string) $item->id,
                    'product_variant_id' => (string) $item->product_variant_id,
                    'sku' => $variant?->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'discount_amount' => (string) $item->discount_amount,
                    'product_title' => $item->product_title_snapshot,
                    'variant_label' => $item->variant_label_snapshot,
                    'personalization_snapshot' => $item->personalization_snapshot,
                    'image_url' => $imageUrl,
                ];
            })->all(),
        ];
    }

    private function handlePixPayment(Order $order): array
    {
        $result = $this->asaasService->createPixPayment($order);

        return [
            'asaas_payment_id' => $result['asaas_payment_id'] ?? null,
            'asaas_customer_id' => $result['asaas_customer_id'] ?? null,
            'asaas_pix_qr_code' => $result['qr_code'],
            'asaas_pix_copy_paste' => $result['copy_paste'],
            'asaas_pix_expires_at' => $result['expires_at'],
        ];
    }

    private function handleBoletoPayment(Order $order): array
    {
        $result = $this->asaasService->createBoletoPayment($order);

        return [
            'asaas_payment_id' => $result['asaas_payment_id'] ?? null,
            'asaas_customer_id' => $result['asaas_customer_id'] ?? null,
            'asaas_boleto_url' => $result['boleto_url'],
            'asaas_boleto_barcode' => $result['barcode'],
            'asaas_boleto_due_date' => $result['due_date'],
        ];
    }

    private function handleCardPayment(Order $order, array $data): array
    {
        $remoteIp = request()->header('X-Forwarded-For') ?? request()->ip() ?? '127.0.0.1';
        $remoteIp = explode(',', $remoteIp)[0];
        $installments = (int) ($data['installments'] ?? 1);
        $useShippingAsBilling = (bool) ($data['use_shipping_as_billing'] ?? true);

        $cardData = ! empty($data['credit_card_token'])
            ? ['token' => $data['credit_card_token']]
            : AsaasService::buildCardData($data['credit_card'] ?? [], $order, $useShippingAsBilling);

        $result = $this->asaasService->createCardPayment($order, $cardData, trim($remoteIp), $installments);

        if (! in_array($result['status'], ['CONFIRMED', 'PENDING', 'RECEIVED'], true)) {
            throw new \RuntimeException('Transação não autorizada');
        }

        if ($order->user_id && ! empty($result['credit_card_token'])) {
            UserPaymentMethod::query()->updateOrCreate(
                [
                    'user_id' => $order->user_id,
                    'asaas_card_token' => $result['credit_card_token'],
                ],
                [
                    'brand' => $result['credit_card_brand'] ?? null,
                    'last4' => $result['credit_card_last4'] ?? null,
                    'holder_name' => $cardData['holder_name'] ?? null,
                    'expiry_month' => $cardData['expiry_month'] ?? null,
                    'expiry_year' => $cardData['expiry_year'] ?? null,
                ]
            );
        }

        return [
            'asaas_payment_id' => $result['asaas_payment_id'] ?? null,
            'asaas_customer_id' => $result['asaas_customer_id'] ?? null,
            'asaas_credit_card_token' => $result['credit_card_token'] ?? null,
            'asaas_credit_card_brand' => $result['credit_card_brand'] ?? null,
            'asaas_credit_card_last4' => $result['credit_card_last4'] ?? null,
        ];
    }

    private function syncUserContact(User $user, array $customer): void
    {
        $updates = [];
        if (empty($user->cpf) && ! empty($customer['cpf'])) {
            $updates['cpf'] = preg_replace('/\D/', '', (string) $customer['cpf']);
        }
        if (empty($user->phone) && ! empty($customer['phone'])) {
            $updates['phone'] = preg_replace('/\D/', '', (string) $customer['phone']);
        }
        if (! empty($updates)) {
            $user->forceFill($updates)->save();
        }
    }

    private function isAdmin(User $user): bool
    {
        try {
            return $user->roles()->where('slug', 'admin')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Validate the coupon code against the (already discounted) subtotal and
     * return the snapshot to persist on the order.
     *
     * @return array{coupon_id?: string, code?: string, discount: float, model?: Coupon}|array{}
     */
    private function resolveCoupon(?string $code, float $subtotal): array
    {
        $code = is_string($code) ? trim($code) : '';
        if ($code === '') {
            return [];
        }

        $subtotalCents = (int) round($subtotal * 100);
        $result = $this->couponService->validate($code, $subtotalCents);

        return [
            'coupon_id' => (string) $result['coupon']->id,
            'code' => $result['coupon']->code,
            'discount' => round($result['discount_cents'] / 100, 2),
            'model' => $result['coupon'],
        ];
    }
}
