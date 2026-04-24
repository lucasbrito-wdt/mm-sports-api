<?php

namespace App\Domains\Commerce\Controllers;

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
