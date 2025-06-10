<?php

namespace App\Domains\ACL\Services;

use App\Domains\ACL\Enums\RoleEnum;
use App\Domains\ACL\Models\Permission;
use App\Domains\ACL\Models\Role;
use App\Domains\Auth\Models\User;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class RoleService extends BaseService
{
    public function __construct(
        private readonly Role $role,
        private readonly User $user,
        private readonly Permission $permission,
    ) {
        $this->setModel($role);
    }

    public function index(array $options = [], ?\Closure $builderCallback = null): LengthAwarePaginator|array
    {
        return parent::index($options, function ($query) {
            $query
                ->whereNot('name', RoleEnum::Admin->value)
                ->when(! auth()->user()->hasRole(RoleEnum::Admin->value), function ($query) {
                    return $query->whereNot('name', RoleEnum::Admin->value);
                });
        });
    }

    public function store(array $data)
    {
        $role = Role::create($data);

        $role->givePermissionTo('auth read');

        if (! empty($data['permissions'])) {
            foreach ($data['permissions'] as $permission) {
                $role->givePermissionTo($permission);
            }
        }

        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('slug')->toArray(),
        ];
    }

    public function show(string $id)
    {
        $role = $this->role->findOrFail($id);

        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('slug')->toArray(),
        ];
    }

    public function update(array $data, string $id)
    {
        $role = $this->findById($id);

        $role->update([
            'name' => $data['name'],
            'slug' => str($data['name'])->slug(),
        ]);

        $permissions = $data['permissions'] ?? [];
        $role->syncPermissions($permissions);

        return $role;
    }

    public function listPermissions(): Collection
    {
        return $this->permission
            ->whereNot('name', 'Admin')
            ->select('name', 'slug')
            ->get()
            ->groupBy('name')
            ->map(function ($items, $groupName) {
                return [
                    'name' => $groupName,
                    'crud' => str($groupName)->camel(),
                    'actions' => $items->pluck('slug')->toArray(),
                ];
            })
            ->values();
    }

    public function atribuirUserRole($userId, $roleId)
    {
        $user = $this->user->find($userId);
        $role = $this->role->find($roleId);

        $user->assignRole($role->name);

        return JsonResource::make($user);
    }

    public function removerUserRole($userId, $roleId)
    {
        $user = $this->user->find($userId);
        $role = $this->role->find($roleId);

        $user->removeRole($role->name);

        return JsonResource::make($user);
    }

    public function atribuirUserRolePermission($userId, $roleId)
    {
        try {
            \DB::beginTransaction();
            $user = $this->user->find($userId);
            $role = $this->role->find($roleId);

            $user->assignRole($role);
            $user->givePermissionTo(Role::findByName($role->name)->permissions()->pluck('name')->toArray());
            $user->refresh();
            \DB::commit();

            return JsonResource::make($user);
        } catch (\Exception) {
            \DB::rollBack();

            return response()->json(['message' => 'Erro ao atribuir permissão ao usuário'], 500);
        }
    }

    public function removeUserRolePermission($userId, $roleId)
    {
        try {
            $user = $this->user->find($userId);
            $role = $this->role->find($roleId);

            $user->removeRole($role->name);
            $user->revokePermissionTo(Role::findByName($role->name)->permissions()->pluck('name')->toArray());

            return JsonResource::make($user);
        } catch (\Exception) {
            \DB::rollBack();

            return response()->json(['message' => 'Erro ao remoção permissão ao usuário'], 500);
        }
    }
}
