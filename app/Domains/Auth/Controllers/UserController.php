<?php

namespace App\Domains\Auth\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Domains\Auth\Requests\UserRequest;
use App\Domains\Auth\Services\UserService;
use App\Domains\Shared\Controller\BaseController;

class UserController extends BaseController
{
    public function __construct(private readonly UserService $userService)
    {
        $this->setACL('users', [
            'list' => ['index'],
            'create' => ['store'],
        ]);
        parent::__construct();
        $this->setService($this->userService);
        $this->setRequest('request', UserRequest::class);
    }

    public function store(Request $request)
    {
        $data = $request->all();

        if (isset($data['role'])) {
            $data['role'] = $data['role']['slug'];
        }
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $this->userService->store($data);
    }

    public function update(Request $request, string $id)
    {
        $data = $request->all();

        if (isset($data['role'])) {
            $data['role'] = $data['role']['slug'];
        }
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $this->userService->update($data, $id);
    }

    public function roles(Request $request)
    {
        $options = $request->all();

        return $this->userService->roles($options);
    }
}
