<?php

namespace App\Domains\Tracking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;

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

            Bus::chain([
                new \App\Domains\Tracking\Jobs\SendToGa4($event->id),
                new \App\Domains\Tracking\Jobs\SendToMetaCapi($event->id),
            ])->onQueue('tracking')->dispatch();
        });
    }
}
