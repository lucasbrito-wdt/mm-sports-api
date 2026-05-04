<?php

namespace App\Domains\Commerce\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Marketing\Models\Coupon;
use App\Domains\Shared\Models\BaseModel;
use App\Domains\Tracking\Models\OrderStatusTransition;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends BaseModel
{
    protected $table = 'orders';

    protected $fillable = [
        'user_id',
        'coupon_id',
        'coupon_code',
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
        'asaas_credit_card_token',
        'asaas_credit_card_brand',
        'asaas_credit_card_last4',
        'paid_at',
        'shipped_at',
        'payment_method',
        'asaas_pix_qr_code',
        'asaas_pix_copy_paste',
        'asaas_pix_expires_at',
        'asaas_boleto_url',
        'asaas_boleto_barcode',
        'asaas_boleto_due_date',
        'notes',
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
            'asaas_pix_expires_at' => 'datetime',
            'asaas_boleto_due_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
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
