<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Shared\Models\BaseModel;

class Banner extends BaseModel
{
    protected $table = 'banners';

    protected $fillable = [
        'internal_title',
        'image_url',
        'destination_url',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
        'device',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
