<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Shared\Models\BaseModel;

class SizeChart extends BaseModel
{
    protected $table = 'size_charts';

    protected $fillable = [
        'name',
        'table_json',
    ];

    protected function casts(): array
    {
        return [
            'table_json' => 'array',
        ];
    }
}
