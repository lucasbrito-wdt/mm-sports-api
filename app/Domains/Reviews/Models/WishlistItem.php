<?php

namespace App\Domains\Reviews\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistItem extends BaseModel
{
    protected $table = 'wishlist_items';

    public $timestamps = true;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'product_variant_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
