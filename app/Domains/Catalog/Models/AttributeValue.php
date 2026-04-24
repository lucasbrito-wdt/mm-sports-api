<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeValue extends BaseModel
{
    protected $table = 'attribute_values';

    protected $fillable = [
        'attribute_id', 'value', 'slug', 'metadata', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'display_order' => 'integer',
        ];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
