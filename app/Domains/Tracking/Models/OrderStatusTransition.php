<?php

namespace App\Domains\Tracking\Models;

use App\Domains\Commerce\Models\Order;
use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusTransition extends BaseModel
{
    public const UPDATED_AT = null;

    protected $table = 'order_status_transitions';

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'source',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
