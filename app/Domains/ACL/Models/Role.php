<?php

namespace App\Domains\ACL\Models;

use App\Domains\ACL\Models\Permission;
use App\Domains\ACL\Traits\HasPermissions;
use App\Domains\Auth\Models\User;
use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @mixin \Eloquent
 */
class Role extends BaseModel
{
    use HasPermissions;

    protected $fillable = ['id', 'name', 'slug', 'created_at', 'updated_at'];

    protected array $list = [
        'id',
        'name',
        'slug',
        'created_at',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
