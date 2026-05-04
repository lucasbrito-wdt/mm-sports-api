<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Marketing\Enums\CouponType;
use App\Domains\Shared\Models\BaseModel;
use Carbon\CarbonInterface;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Coupon extends BaseModel
{
    use HasFactory;

    protected $table = 'coupons';

    protected static function newFactory()
    {
        return CouponFactory::new();
    }

    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'min_subtotal',
        'max_discount',
        'usage_limit',
        'usage_count',
        'starts_at',
        'expires_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'active' => 'bool',
        ];
    }

    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = $value === null ? null : strtoupper(trim($value));
    }

    public function isWithinWindow(?CarbonInterface $now = null): bool
    {
        $now ??= now();
        if ($this->starts_at !== null && $now->lt($this->starts_at)) {
            return false;
        }
        if ($this->expires_at !== null && $now->gt($this->expires_at)) {
            return false;
        }

        return true;
    }

    public function hasUsesLeft(): bool
    {
        return $this->usage_limit === null || $this->usage_count < $this->usage_limit;
    }

    /**
     * Calculate discount in cents for a given subtotal in cents.
     * Returns the discount amount (clamped to subtotal and max_discount).
     */
    public function discountForSubtotalCents(int $subtotalCents): int
    {
        if ($subtotalCents <= 0) {
            return 0;
        }

        $discount = match ($this->type) {
            CouponType::Percentage => (int) floor($subtotalCents * (float) $this->value / 100),
            CouponType::Fixed => (int) round((float) $this->value * 100),
        };

        if ($this->max_discount !== null) {
            $cap = (int) round((float) $this->max_discount * 100);
            $discount = min($discount, $cap);
        }

        return min($discount, $subtotalCents);
    }
}
