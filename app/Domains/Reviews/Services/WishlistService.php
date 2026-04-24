<?php

namespace App\Domains\Reviews\Services;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Reviews\Models\WishlistItem;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Tracking\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WishlistService extends BaseService
{
    public function __construct(
        private readonly WishlistItem $wishlistItem,
        private readonly ProductVariant $productVariant,
        private readonly AnalyticsService $analyticsService,
    ) {
        $this->setModel($this->wishlistItem);
    }

    public function listForUser(string $userId): array
    {
        $rows = $this->wishlistItem->newQuery()
            ->where('user_id', $userId)
            ->with('productVariant.product')
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'data' => $rows->map(function (WishlistItem $w) {
                $v = $w->productVariant;
                $p = $v?->product;

                return [
                    'id' => (string) $w->id,
                    'product_variant_id' => (string) $w->product_variant_id,
                    'product_id' => $p ? (string) $p->id : null,
                    'title' => $p?->title,
                    'sku' => $v?->sku,
                    'price' => $v?->price,
                ];
            })->all(),
        ];
    }

    public function add(string $userId, string $productVariantId, Request $request): WishlistItem
    {
        $variant = $this->productVariant->newQuery()->where('id', $productVariantId)->where('is_active', true)->first();
        if (! $variant) {
            throw new NotFoundHttpException('Product variant not found');
        }

        $item = $this->wishlistItem->newQuery()->firstOrCreate(
            [
                'user_id' => $userId,
                'product_variant_id' => $productVariantId,
            ],
            ['id' => (string) Str::ulid()]
        );

        if ($item->wasRecentlyCreated) {
            $this->analyticsService->track('wishlist_added', $userId, [
                'product_variant_id' => $productVariantId,
            ], 'api', $request);
        }

        return $item;
    }

    public function remove(string $userId, string $productVariantId, Request $request): void
    {
        $this->wishlistItem->newQuery()
            ->where('user_id', $userId)
            ->where('product_variant_id', $productVariantId)
            ->delete();

        $this->analyticsService->track('wishlist_removed', $userId, [
            'product_variant_id' => $productVariantId,
        ], 'api', $request);
    }
}
