<?php

namespace App\Domains\Reviews\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Commerce\Models\Order;
use App\Domains\Commerce\Models\OrderItem;
use App\Domains\Reviews\Enums\ReviewModerationStatus;
use App\Domains\Reviews\Models\ProductReview;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Tracking\Services\AnalyticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductReviewService extends BaseService
{
    public function __construct(
        private readonly ProductReview $productReview,
        private readonly Order $order,
        private readonly OrderItem $orderItem,
        private readonly AnalyticsService $analyticsService,
    ) {
        $this->setModel($this->productReview);
    }

    public function listApprovedForProduct(string $productId): array
    {
        $rows = $this->productReview->newQuery()
            ->where('product_id', $productId)
            ->where('moderation_status', ReviewModerationStatus::Approved)
            ->orderByDesc('created_at')
            ->get();

        return [
            'data' => $rows->map(fn (ProductReview $r) => $this->transformPublic($r))->all(),
        ];
    }

    public function createForUser(User $user, array $data): ProductReview
    {
        $orderId = $data['order_id'] ?? null;
        $isVerified = false;
        if ($orderId) {
            $isVerified = $this->isVerifiedPurchase($user->id, $orderId, $data['product_id']);
        }

        $review = $this->productReview->newQuery()->create([
            'id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'product_id' => $data['product_id'],
            'order_id' => $orderId,
            'rating' => (int) $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'moderation_status' => ReviewModerationStatus::Pending,
            'is_verified_purchase' => $isVerified,
        ]);

        $this->analyticsService->track(
            'review_submitted',
            $user->id,
            [
                'product_id' => (string) $data['product_id'],
                'rating' => (int) $data['rating'],
            ],
            'api',
            request()
        );

        return $review;
    }

    private function isVerifiedPurchase(string $userId, string $orderId, string $productId): bool
    {
        $order = $this->order->newQuery()
            ->where('id', $orderId)
            ->where('user_id', $userId)
            ->first();
        if (! $order) {
            return false;
        }

        return $this->orderItem->newQuery()
            ->where('order_id', $order->id)
            ->whereHas('productVariant', fn (Builder $q) => $q->where('product_id', $productId))
            ->exists();
    }

    private function transformPublic(ProductReview $r): array
    {
        return [
            'id' => (string) $r->id,
            'rating' => $r->rating,
            'title' => $r->title,
            'body' => $r->body,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
