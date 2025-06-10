<?php

namespace App\Domains\ACL\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;

class RoleRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [];
    }

    public function view(): array
    {
        return [];
    }

    public function store(): array
    {
        return [
            #'name' => 'required|string|max:255|unique:'.config('permission.table_names.roles', 'roles').',name,'.$this->request->get("id"),
        ];
    }

    public function update(): array
    {
        return [
            #'name' => 'required|string|max:255|unique:'.config('permission.table_names.roles', 'roles').',name,'.$this->request->get("id"),
        ];
    }

    public function destroy(): array
    {
        return [];
    }
}
