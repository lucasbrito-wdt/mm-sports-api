<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends BaseModel
{
    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'compare_at_price',
        'stock_quantity',
        'weight_grams',
        'length_cm',
        'width_cm',
        'height_cm',
        'attribute_payload',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attribute_payload' => 'array',
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'is_active' => 'bool',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
