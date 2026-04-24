<?php

namespace App\Domains\Commerce\Services;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Commerce\Models\Order;
use App\Domains\Reviews\Enums\ReviewModerationStatus;
use App\Domains\Reviews\Models\ProductReview;

class DashboardAdminService
{
    /**
     * @return array{
     *     orders_by_status: array<string, int>,
     *     orders_total: int,
     *     recent_orders: list<array{id: string, status: string, grand_total: string|null, created_at: string|null}>,
     *     products_total: int,
     *     products_published: int,
     *     reviews_pending: int
     * }
     */
    public function summary(): array
    {
        $ordersByStatus = Order::query()
            ->toBase()
            ->from('orders')
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->status => (int) $r->c])
            ->all();

        $ordersTotal = (int) Order::query()->count();

        $recentOrders = Order::query()
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Order $o) => [
                'id' => (string) $o->id,
                'status' => $o->status->value,
                'grand_total' => $o->grand_total !== null ? (string) $o->grand_total : null,
                'created_at' => $o->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'orders_by_status' => $ordersByStatus,
            'orders_total' => $ordersTotal,
            'recent_orders' => $recentOrders,
            'products_total' => (int) Product::query()->count(),
            'products_published' => (int) Product::query()
                ->where('status', ProductStatus::Published)
                ->count(),
            'reviews_pending' => (int) ProductReview::query()
                ->where('moderation_status', ReviewModerationStatus::Pending)
                ->count(),
        ];
    }
}
