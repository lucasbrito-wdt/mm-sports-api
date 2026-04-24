<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends BaseModel
{
    protected $table = 'products';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'origin',
        'allows_personalization',
        'size_chart_id',
        'status',
        'ncm',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'allows_personalization' => 'bool',
            'origin' => ProductOrigin::class,
            'status' => ProductStatus::class,
        ];
    }

    public function sizeChart(): BelongsTo
    {
        return $this->belongsTo(SizeChart::class, 'size_chart_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function personalizationOptions(): HasMany
    {
        return $this->hasMany(ProductPersonalizationOption::class);
    }

    /**
     * Alias for nested admin routes `products.options.*` (apiResource->scoped()).
     */
    public function options(): HasMany
    {
        return $this->personalizationOptions();
    }
}
