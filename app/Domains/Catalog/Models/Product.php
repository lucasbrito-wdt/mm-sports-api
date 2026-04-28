<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Observers\ProductAttributeSyncObserver;
use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Casts\Attribute as CastsAttribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
        'attribute_value_ids',
        'category_id',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
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

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'product_attribute_values',
            'product_id',
            'attribute_value_id'
        );
    }

    public function variantAxes(): BelongsToMany
    {
        return $this->belongsToMany(
            Attribute::class,
            'product_variant_axes',
            'product_id',
            'attribute_id'
        )->withPivot('display_order')->orderByPivot('display_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('display_order');
    }

    /**
     * @param  string[]  $valueIds  IDs de attribute_values (facetas no produto)
     */
    public function syncAttributeValues(array $valueIds): void
    {
        $this->attributeValues()->sync($valueIds);
        $this->unsetRelation('attributeValues');
        ProductAttributeSyncObserver::recomputeAttributeValueIds($this);
    }

    public function refreshAttributeValueIdsCache(): void
    {
        ProductAttributeSyncObserver::recomputeAttributeValueIds($this);
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
                $connectionName = $this->getConnectionName();
                $driver = DB::connection($connectionName)->getDriverName();
                if ($driver === 'pgsql') {
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
