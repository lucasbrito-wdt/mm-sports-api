<?php

namespace App\Domains\ACL\Traits;

use App\Domains\ACL\Events\PermissionGranted;
use App\Domains\ACL\Events\PermissionRevoked;
use App\Domains\ACL\Models\Permission;
use Illuminate\Support\Facades\Cache;

trait HasPermissions
{
    protected function belongsToManyPermissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    protected function cachedPermissions()
    {
        if (!$this->exists || !$this->id) {
            return collect();
        }

        return Cache::remember("role_{$this->id}_permissions", 3600, function () {
            return $this->belongsToManyPermissions()->get();
        });
    }

    public function permissions()
    {
        return $this->cachedPermissions();
    }

    public function hasDirectPermission($permission)
    {
        $permissions = $this->permissions();

        if (is_string($permission)) {
            return $permissions->contains('slug', $permission);
        }

        return $permissions->contains('id', $permission->id);
    }

    /**
     * Atribui uma permission à role.
     *
     * @param  array|string  $permissions
     */
    public function givePermissionTo($permission): void
    {
        $this->clearPermissionsCache();

        if (is_string($permission)) {
            $permission = Permission::whereSlug($permission)->firstOrFail();
        }

        $this->permissions()->attach($permission);

        event(new PermissionGranted($this, $permission));
    }

    /**
     * Remove uma permission da role.
     *
     * @param  array|string  $permissions
     */
    public function revokePermissionTo($permission): void
    {
        $this->clearPermissionsCache();

        if (is_string($permission)) {
            $permission = Permission::whereSlug($permission)->firstOrFail();
        }

        $this->permissions()->detach($permission);

        event(new PermissionRevoked($this, $permission));
    }

    public function syncPermissions($permissions): void
    {
        $this->clearPermissionsCache();

        $permissionIds = [];

        $this->permissions()->detach();

        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $permission = Permission::whereSlug($permission)->firstOrFail();
            }
            $permissionIds[] = $permission->id;
        }

        $this->permissions()->sync($permissionIds);
    }

    protected function clearPermissionsCache(): void
    {
        if ($this->id) {
            Cache::forget("role_{$this->id}_permissions");
        }
    }
}
