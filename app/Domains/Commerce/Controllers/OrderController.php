<?php

namespace App\Domains\Commerce\Controllers;

use App\Domains\Commerce\Models\Order;
use App\Domains\Commerce\Requests\StoreOrderRequest;
use App\Domains\Commerce\Services\CheckoutQuoteService;
use App\Domains\Commerce\Services\OrderService;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly CheckoutQuoteService $checkoutQuoteService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $out = $this->orderService->listForUser($request->user(), $request->all());

        return response()->json($out);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $out = $this->orderService->detailForUser($request->user(), $id);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (InvalidArgumentException) {
            abort(403);
        }

        return response()->json($out);
    }

    public function place(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->createFromUser($request->user(), $request->validated());
            $this->orderService->dispatchOrderPlacedEmail($order);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $billingType = strtoupper($request->input('billing_type'));

        $payload = ['order_id' => (string) $order->id];

        if ($billingType === 'PIX') {
            $payload['pix_qr_code'] = $order->asaas_pix_qr_code;
            $payload['pix_copy_paste'] = $order->asaas_pix_copy_paste;
            $payload['expires_at'] = $order->asaas_pix_expires_at?->toIso8601String();
        } elseif ($billingType === 'BOLETO') {
            $payload['boleto_url'] = $order->asaas_boleto_url;
            $payload['barcode'] = $order->asaas_boleto_barcode;
            $payload['due_date'] = $order->asaas_boleto_due_date?->toDateString();
        } else {
            $payload['status'] = $order->status->value;
        }

        return response()->json($payload, 201);
    }

    public function quoteShipping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'postal_code' => ['required', 'string', 'min:8', 'max:9'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $quote = $this->checkoutQuoteService->computeQuote($request->user()?->id ?? 'anonymous', [
                'items' => $validated['items'],
                'destination_postal_code' => $validated['postal_code'],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $shipping = $quote['shipping'];

        return response()->json([
            'shipping' => $shipping,
            'shipping_options' => $shipping['options'] ?? [
                [
                    'service_code' => $shipping['service_code'] ?? 'STUB',
                    'service_name' => $shipping['service_name'] ?? 'Frete',
                    'price' => (float) $quote['shipping_total'],
                    'eta_days' => $shipping['eta_days'] ?? 7,
                ],
            ],
            'subtotal' => $quote['subtotal'],
            'discount_total' => $quote['discount_total'],
            'shipping_total' => $quote['shipping_total'],
            'grand_total' => $quote['grand_total'],
        ]);
    }

    public function orderStatus(Request $request, string $orderId): JsonResponse
    {
        $order = Order::query()->whereKey($orderId)->first();

        if (! $order) {
            return response()->json(['message' => 'Pedido não encontrado'], 404);
        }

        $user = $request->user();
        if ((string) $order->user_id !== (string) $user->id) {
            $isAdmin = false;
            try {
                $isAdmin = $user->roles()->where('slug', 'admin')->exists();
            } catch (Throwable) {
            }
            if (! $isAdmin) {
                abort(403);
            }
        }

        return response()->json([
            'order_id' => (string) $order->id,
            'status' => $order->status->value,
        ]);
    }

    public function timeline(Request $request, string $id): JsonResponse
    {
        try {
            $out = $this->orderService->timelineForUser($request->user(), $id);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (InvalidArgumentException) {
            abort(403);
        }

        return response()->json($out);
    }
}
