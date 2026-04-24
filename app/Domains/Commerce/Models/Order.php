<?php

namespace App\Domains\Commerce\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Shared\Models\BaseModel;
use App\Domains\Tracking\Models\OrderStatusTransition;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends BaseModel
{
    protected $table = 'orders';

    protected $fillable = [
        'user_id',
        'status',
        'subtotal',
        'discount_total',
        'shipping_total',
        'grand_total',
        'shipping_service_code',
        'shipping_quote_json',
        'shipping_address_snapshot',
        'correios_tracking_code',
        'asaas_customer_id',
        'asaas_payment_id',
        'paid_at',
        'shipped_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'shipping_quote_json' => 'array',
            'shipping_address_snapshot' => 'array',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(OrderStatusTransition::class, 'order_id');
    }
}
