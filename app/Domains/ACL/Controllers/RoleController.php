<?php

namespace App\Domains\ACL\Controllers;

use App\Domains\ACL\Requests\RoleRequest;
use App\Domains\ACL\Services\RoleService;
use App\Domains\Shared\Controller\BaseController;
use Illuminate\Http\Request;

class RoleController extends BaseController
{
    public function __construct(private readonly RoleService $roleService)
    {
        $this->setACL('roles', 'admin');
        parent::__construct();
        $this->setService($this->roleService);
        $this->setRequest('request', RoleRequest::class);
    }

    public function store(Request $request)
    {
        $data = $request->all();

        $data['name'] = str($data['name'])->title();
        $data['slug'] = str($data['name'])->slug();

        return $this->roleService->store($data);
    }

    public function listPermissions()
    {
        return $this->roleService->listPermissions();
    }
}
