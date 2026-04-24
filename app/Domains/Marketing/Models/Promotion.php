<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Marketing\Enums\PromotionType;
use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends BaseModel
{
    protected $table = 'promotions';

    protected $fillable = [
        'name',
        'type',
        'value',
        'starts_at',
        'ends_at',
        'is_active',
        'min_order_total',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'value' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'bool',
            'min_order_total' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PromotionItem::class);
    }
}
