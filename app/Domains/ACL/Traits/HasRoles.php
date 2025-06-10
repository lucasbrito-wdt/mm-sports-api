<?php

namespace App\Domains\ACL\Traits;

use App\Domains\ACL\Events\RoleAssigned;
use App\Domains\ACL\Events\RoleRemoved;
use App\Domains\ACL\Models\Role;
use Exception;
use Illuminate\Support\Facades\Cache;

trait HasRoles
{
    public function belongsToManyRoles()
    {
        return $this->belongsToMany(Role::class);
    }

    protected function cachedRoles()
    {
        return Cache::remember("user_{$this->id}_roles", 3600, function () {
            return $this->belongsToManyRoles()->get(['id', 'name', 'slug']);
        });
    }

    /**
     * Relação many-to-many entre User e Role.
     */
    public function roles()
    {
        return $this->cachedRoles();
    }

    /**
     * @throws Exception
     */
    public function permissions()
    {
        if (! isset($this->role)) {
            throw new Exception('Usuário não possuí uma role');
        }

        return $this->cachedRoles()
            ->where('slug', '===', $this->role['slug'])
            ->first()
            ->permissions
            ->select(['name', 'slug'])
            ->toArray();
    }

    /**
     * Atribui uma role ao usuário.
     *
     * @param  array|string  $roles
     */
    public function assignRole($role): void
    {
        $this->clearRolesCache();

        if (is_string($role)) {
            $role = Role::whereSlug($role)->firstOrFail();
        }

        $this->belongsToManyRoles()->attach($role);

        event(new RoleAssigned($this, $role));
    }

    /**
     * Remove uma role do usuário.
     *
     * @param  string|array  $roles
     */
    public function removeRole($role): void
    {
        $this->clearRolesCache();

        if (is_string($role)) {
            $role = Role::whereSlug($role)->firstOrFail();
        }

        $this->belongsToManyRoles()->detach($role);

        event(new RoleRemoved($this, $role));
    }

    /**
     * Verifica se o usuário possui uma role específica.
     *
     * @param  array|string  $roles
     */
    public function hasRole($role): bool
    {
        $roles = $this->cachedRoles();

        if (is_string($role)) {
            return $roles->contains('slug', $role);
        }

        return $roles->contains('id', $role->id);
    }

    /**
     * Verifica se o usuário possui uma permission específica através das roles.
     *
     * @param  string  $permission
     */
    public function hasPermission(string|array $permission): bool
    {
        $permissions = collect($this->permissions());

        return $permissions->contains(function ($value, $key) use ($permission) {
            if (is_array($permission)) {
                return in_array($value['slug'], $permission);
            }

            return $value['slug'] === $permission;
        });
    }

    public function syncRoles($roles): void
    {
        $this->clearRolesCache();

        $roleIds = [];

        if (is_string($roles)) {
            $roleIds[] = Role::whereSlug($roles)->firstOrFail()->id;
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if (is_string($role)) {
                    $role = Role::whereSlug($role)->firstOrFail();
                }
                $roleIds[] = $role->id;
            }
        }

        $this->belongsToManyRoles()->sync($roleIds);
    }

    protected function clearRolesCache(): void
    {
        Cache::forget("user_{$this->id}_roles");
    }
}
