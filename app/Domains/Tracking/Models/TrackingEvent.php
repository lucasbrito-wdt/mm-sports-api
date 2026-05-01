<?php

namespace App\Domains\Tracking\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Tracking\Jobs\SendToGa4;
use App\Domains\Tracking\Jobs\SendToMetaCapi;

class TrackingEvent extends Model
{
    protected $table = 'events';
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'properties'      => 'array',
            'event_timestamp' => 'datetime',
        ];
    }

    private const REPLICATED_EVENTS = [
        'view_item', 'add_to_cart', 'begin_checkout',
        'add_payment_info', 'purchase', 'refund', 'sign_up',
    ];

    protected static function booted(): void
    {
        static::created(function (TrackingEvent $event) {
            if (!in_array($event->event_name, self::REPLICATED_EVENTS, true)) {
                return;
            }

            SendToGa4::dispatch($event->id)->onQueue('tracking');
            SendToMetaCapi::dispatch($event->id)->onQueue('tracking');
        });
    }
}
