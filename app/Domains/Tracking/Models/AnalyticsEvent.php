<?php

namespace App\Domains\Tracking\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends BaseModel
{
    public const UPDATED_AT = null;

    protected $table = 'analytics_events';

    protected $fillable = [
        'user_id',
        'name',
        'properties',
        'source',
        'request_id',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
