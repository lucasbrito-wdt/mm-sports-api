<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends BaseModel
{
    protected $table = 'attributes';

    protected $fillable = [
        'code', 'label', 'type', 'input_type',
        'is_filterable', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'input_type' => AttributeInputType::class,
            'is_filterable' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('display_order');
    }
}
