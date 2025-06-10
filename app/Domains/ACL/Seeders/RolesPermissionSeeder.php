<?php

namespace App\Domains\ACL\Seeders;

use App\Domains\ACL\Enums\RoleEnum;
use App\Domains\ACL\Models\Permission;
use App\Domains\ACL\Models\Role;
use Illuminate\Database\Seeder;

class RolesPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Populate permissions from config
        collect(config('permission_list'))->each(function ($permissionValue, $permissionTitle) {
            foreach ($permissionValue as $value) {
                Permission::updateOrCreate(['name' => $permissionTitle, 'slug' => $value], ['name' => $permissionTitle, 'slug' => $value]);
            }
        });

        // Populate roles from RoleEnum
        collect(RoleEnum::cases())->each(function ($roleEnum) {
            Role::updateOrCreate(['name' => $roleEnum->getRoleName(), 'slug' => $roleEnum->value], ['name' => $roleEnum->getRoleName(), 'slug' => $roleEnum->value]);
        });

        // Assign permissions to roles
        Role::all()->map(function ($role) {
            $roleEnum = RoleEnum::from($role->slug);

            collect($roleEnum->getPermissions())
                ->each(function ($p) use (&$role) {
                    $permission = Permission::whereSlug($p)->firstOrFail();
                    $role->givePermissionTo($permission);
                });
        });
    }
}
