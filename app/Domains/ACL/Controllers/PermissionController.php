<?php

namespace App\Domains\ACL\Controllers;

use App\Domains\ACL\Requests\PermissionRequest;
use App\Domains\ACL\Services\PermissionService;
use App\Domains\Shared\Controller\BaseController;

class PermissionController extends BaseController
{
    function __construct(private readonly PermissionService $permissionService)
    {
        $this->setACL('permission', 'admin');

        parent::__construct();
        $this->setService($this->permissionService);
        $this->setRequest('request', PermissionRequest::class);
    }

    public function storeAll(PermissionRequest $request)
    {
        $data = $request->all();
        $permissions = [];
        foreach ($data['actions'] as $action) {
            $permissions[] = [
                'name' => $data['name'],
                'slug' => $action,
            ];
        }
        return $this->permissionService->storeAll($permissions);
    }

    public function updateAll(PermissionRequest $request)
    {
        $data = $request->all();
        $permissions = [];
        if (isset($data['actions'])) {
            foreach ($data['actions'] as $action) {
                $permissions[] = [
                    'name' => $data['name'],
                    'slug' => $action,
                ];
            }
        }
        return $this->permissionService->updateAll($permissions, $data['name']);
    }

    public function destroyAll($name)
    {
        return $this->permissionService->destroyAll($name);
    }

    public function listActions()
    {
        return $this->permissionService->listActions();
    }
}
