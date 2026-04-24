<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Catalog\Enums\PersonalizationOptionType;
use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPersonalizationOption extends BaseModel
{
    protected $table = 'product_personalization_options';

    protected $fillable = [
        'product_id',
        'type',
        'label',
        'is_required',
        'additional_price',
        'max_length',
        'options_json',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => PersonalizationOptionType::class,
            'is_required' => 'bool',
            'additional_price' => 'decimal:2',
            'options_json' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
