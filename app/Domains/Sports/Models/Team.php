<?php

namespace App\Domains\Sports\Models;

use App\Domains\Shared\Models\BaseModel;

class Team extends BaseModel
{
    protected $table = 'teams';

    protected $fillable = [
        'external_id',
        'name',
        'short_name',
        'name_for_url',
        'symbolic_name',
        'sport_id',
        'country_id',
        'popularity_rank',
        'color',
        'logo_url',
        'image_version',
    ];

    protected function casts(): array
    {
        return [
            'external_id' => 'int',
            'sport_id' => 'int',
            'country_id' => 'int',
            'popularity_rank' => 'int',
            'image_version' => 'int',
        ];
    }
}
