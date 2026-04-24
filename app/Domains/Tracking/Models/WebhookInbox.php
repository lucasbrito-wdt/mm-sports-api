<?php

namespace App\Domains\Tracking\Models;

use App\Domains\Commerce\Models\Order;
use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookInbox extends BaseModel
{
    public const UPDATED_AT = null;

    protected $table = 'webhook_inbox';

    protected $fillable = [
        'provider',
        'external_event_id',
        'payload_hash',
        'order_id',
        'processing_result',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
