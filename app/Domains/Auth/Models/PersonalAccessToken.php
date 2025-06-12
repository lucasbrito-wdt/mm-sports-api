<?php

namespace App\Domains\Auth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 *
 *
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $tokenable
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken query()
 * @mixin \Eloquent
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUlids;
}
