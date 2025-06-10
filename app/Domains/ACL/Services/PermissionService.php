<?php

namespace App\Domains\ACL\Services;

use App\Domains\ACL\Enums\PermissionActionsEnum;
use App\Domains\ACL\Models\Permission;
use App\Domains\Shared\Services\BaseService;
use DB;
use Str;

class PermissionService extends BaseService
{
    public function __construct(private readonly Permission $permission)
    {
        $this->setModel($this->permission);
    }

    public function index(array $options = [], ?\Closure $builderCallback = null): array
    {
        $paginatedPermissions = $this->permission
            ->select(
                [
                    'name',
                    DB::raw(
                        (DB::getDriverName() === 'pgsql' ? 'STRING_AGG(slug, \',\')' : 'GROUP_CONCAT(slug)') . ' as slugs',
                    ),
                ],
            )
            ->groupBy('name')
            ->paginate(10);

        // Transformamos cada item do paginador
        $paginatedTransformed = $paginatedPermissions
            ->getCollection()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'crud' => str($item->name)->camel(),
                    'actions' => explode(',', $item->slugs),
                ];
            })
            ->values();

        // Substituímos a coleção no paginador com a coleção transformada
        $paginatedPermissions->setCollection($paginatedTransformed);

        return [
            'data' => $paginatedPermissions->items(),
            'total' => $paginatedPermissions->total(),
            'page' => $paginatedPermissions->currentPage(),
        ];
    }

    public function storeAll(array $data): array
    {
        foreach ($data as $permission) {
            $this->permission->create($permission);
        }

        return [
            'name' => $data[0]['name'],
            'slug' => Str::camel($data[0]['name']),
            'actions' => collect($data)->pluck('slug')->toArray(),
        ];
    }

    public function updateAll(array $data, string $name): array
    {
        $this->destroyAll($name);

        if (count($data) > 0) {
            foreach ($data as $permission) {
                $this->permission->create($permission);
            }

            return [
                'name' => $data[0]['name'],
                'crud' => Str::camel($data[0]['name']),
                'actions' => collect($data)->pluck('slug')->toArray(),
            ];
        }

        return [];
    }

    public function destroyAll(string $name)
    {
        return $this->permission
            ->whereName($name)
            ->delete();
    }

    public function listActions(): array
    {
        return $this->getPermissionActions();
    }

    private function getPermissionActions(): array
    {
        return collect(PermissionActionsEnum::cases())->reduce(function ($acc, $item) {
            $acc[] = $this->mapPermissionAction($item);

            return $acc;
        }, []);
    }

    private function mapPermissionAction($item): array
    {
        return [
            'id' => $item->value,
            'title' => $item->label(),
        ];
    }
}
