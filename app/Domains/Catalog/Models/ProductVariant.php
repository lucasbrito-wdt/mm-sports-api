<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Casts\Attribute as CastsAttribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'variant_attribute_values',
            'product_variant_id',
            'attribute_value_id'
        )->withPivot('attribute_id');
    }

    protected function attributeValueIds(): CastsAttribute
    {
        return CastsAttribute::make(
            get: function ($value): array {
                if ($value === null || $value === '' || $value === '{}') {
                    return [];
                }
                if (is_array($value)) {
                    return $value;
                }
                if (! is_string($value)) {
                    return [];
                }
                if (str_starts_with($value, '[')) {
                    return json_decode($value, true) ?? [];
                }
                $inner = trim($value, '{}');
                if ($inner === '') {
                    return [];
                }

                return array_map('trim', explode(',', $inner));
            },
            set: function (array $ids): string {
                $ids = array_values($ids);
                if ($this->getConnection()->getDriverName() === 'pgsql') {
                    if ($ids === []) {
                        return '{}';
                    }

                    return '{'.implode(',', $ids).'}';
                }

                return json_encode($ids);
            },
        );
    }
}
