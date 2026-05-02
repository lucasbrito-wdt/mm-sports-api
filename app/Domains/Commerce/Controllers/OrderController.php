<?php

namespace App\Domains\Commerce\Controllers;

use App\Domains\Commerce\Models\Order;
use App\Domains\Commerce\Requests\GuestOrderRequest;
use App\Domains\Commerce\Requests\StoreOrderRequest;
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
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'id' => (string) $order->id,
            'status' => $order->status->value,
            'grand_total' => (string) $order->grand_total,
            'asaas_payment_id' => $order->asaas_payment_id,
        ]], 201);
    }

    public function placeGuest(GuestOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->createFromGuest(
                $request->input('customer'),
                $request->validated()
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
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

    public function orderStatus(string $orderId): JsonResponse
    {
        $order = Order::query()->whereKey($orderId)->first();

        if (! $order) {
            return response()->json(['message' => 'Pedido não encontrado'], 404);
        }

        return response()->json([
            'order_id' => (string) $order->id,
            'status'   => $order->status->value,
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
