<?php

namespace App\Domains\Reviews\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Models\Product;
use App\Domains\Commerce\Models\Order;
use App\Domains\Reviews\Enums\ReviewModerationStatus;
use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends BaseModel
{
    protected $table = 'product_reviews';

    protected $fillable = [
        'user_id',
        'product_id',
        'order_id',
        'rating',
        'title',
        'body',
        'moderation_status',
        'is_verified_purchase',
        'store_reply',
        'store_replied_at',
    ];

    protected function casts(): array
    {
        return [
            'moderation_status' => ReviewModerationStatus::class,
            'is_verified_purchase' => 'bool',
            'store_replied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
