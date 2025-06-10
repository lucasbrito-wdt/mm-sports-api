<?php

namespace App\Domains\ACL\Enums;

enum RoleEnum: string
{
    case Admin = 'admin';
    case AdminSystem = 'admin-system';
    case User = 'user';

    public function getPermissions(): array
    {
        return match ($this) {
            self::Admin => [
                ...config('permission_list.auth'),
                ...config('permission_list.manage'),
            ],
            self::AdminSystem => [
                ...config('permission_list.auth'),
                ...config('permission_list.profile'),
                ...config('permission_list.users'),
            ],
            self::User => [
                ...config('permission_list.auth'),
                ...config('permission_list.profile'),
            ],
        };
    }

    public function getRoleName(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::AdminSystem => 'Admin System',
            self::User => 'User',
        };
    }
}
