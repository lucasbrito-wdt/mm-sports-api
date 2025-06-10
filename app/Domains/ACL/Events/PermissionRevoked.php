<?php

namespace App\Domains\ACL\Events;

use App\Domains\ACL\Models\Permission;
use App\Domains\ACL\Models\Role;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PermissionRevoked
{
    use Dispatchable, SerializesModels;

    public Role $role;
    public Permission $permission;

    public function __construct(Role $role, Permission $permission)
    {
        $this->role = $role;
        $this->permission = $permission;
    }
}
