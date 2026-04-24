<?php

namespace App\Domains\Reviews\Services;

use App\Domains\Reviews\Enums\ReviewModerationStatus;
use App\Domains\Reviews\Models\ProductReview;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Tracking\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class ProductReviewAdminService extends BaseService
{
    public function __construct(
        private readonly ProductReview $productReview,
        private readonly AuditLogger $auditLogger,
    ) {
        $this->setModel($this->productReview);
    }

    public function index(array $options = [], ?\Closure $builderCallback = null)
    {
        $enrich = function ($query) {
            $query->with(['user:id,name,email', 'product:id,title,slug']);
        };
        if ($builderCallback) {
            $enrich = function ($query) use ($enrich, $builderCallback) {
                $enrich($query);
                $builderCallback($query);
            };
        }
        if (empty($options['sort_by'])) {
            $options['sort_by'] = 'created_at';
            $options['sort_order'] = 'desc';
        }

        return parent::index($options, $enrich);
    }

    public function show(string $id)
    {
        $review = $this->findById($id);

        return $review->load(['user', 'product', 'order']);
    }

    public function moderate(string $id, array $data): ProductReview
    {
        return DB::transaction(function () use ($id, $data) {
            /** @var ProductReview $review */
            $review = $this->findById($id);
            $old = $this->reviewSnapshot($review);

            $review->moderation_status = ReviewModerationStatus::from($data['moderation_status']);
            if (array_key_exists('store_reply', $data) && $data['store_reply'] !== null) {
                $review->store_reply = $data['store_reply'];
                if ($data['store_reply'] !== '') {
                    $review->store_replied_at = now();
                }
            }
            $review->save();
            $review->refresh();
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'product_reviews.moderate',
                $review,
                $old,
                $this->reviewSnapshot($review),
                request()
            );

            return $review->load(['user', 'product', 'order']);
        });
    }

    private function reviewSnapshot(ProductReview $review): array
    {
        $a = $review->getAttributes();
        if (isset($a['moderation_status']) && $a['moderation_status'] instanceof \UnitEnum) {
            $a['moderation_status'] = $a['moderation_status'] instanceof \BackedEnum
                ? $a['moderation_status']->value
                : (string) $a['moderation_status'];
        }

        return $a;
    }
}
