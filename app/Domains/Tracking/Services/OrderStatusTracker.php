<?php

namespace App\Domains\Tracking\Services;

use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Models\Order;
use App\Domains\Tracking\Models\OrderStatusTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderStatusTracker
{
    public function __construct(
        private readonly OrderStatusTransition $orderStatusTransition,
    ) {}

    public function record(Order $order, ?string $from, string $to, string $source, ?array $meta = null): void
    {
        DB::transaction(function () use ($order, $from, $to, $source, $meta) {
            $toEnum = OrderStatus::from($to);
            $order->status = $toEnum;
            $order->save();

            $this->orderStatusTransition->newQuery()->create([
                'id' => (string) Str::ulid(),
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $to,
                'source' => $source,
                'meta' => $meta,
            ]);
        });
    }
}
