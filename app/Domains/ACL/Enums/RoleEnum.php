<?php

namespace App\Domains\ACL\Enums;

enum RoleEnum: string
{
    case Admin = 'admin';
    case AdminSystem = 'admin-system';
    case Manager = 'manager';
    case CatalogManager = 'catalog-manager';
    case Marketing = 'marketing';
    case OrderOps = 'order-ops';
    case Support = 'support';
    case User = 'user';

    public function getPermissions(): array
    {
        return match ($this) {
            self::Admin => [
                ...config('permission_list.auth'),
                ...config('permission_list.manage'),
                ...config('permission_list.catalog'),
                ...config('permission_list.marketing'),
                ...config('permission_list.commerce'),
                ...config('permission_list.dashboard'),
                ...config('permission_list.product_reviews'),
                ...config('permission_list.tracking'),
            ],
            self::AdminSystem => [
                ...config('permission_list.auth'),
                ...config('permission_list.profile'),
                ...config('permission_list.users'),
            ],
            self::Manager => [
                ...config('permission_list.auth'),
                ...config('permission_list.profile'),
                ...config('permission_list.catalog'),
                ...config('permission_list.marketing'),
                ...config('permission_list.commerce'),
                ...config('permission_list.dashboard'),
                ...config('permission_list.product_reviews'),
                ...config('permission_list.tracking'),
            ],
            self::CatalogManager => [
                ...config('permission_list.auth'),
                ...config('permission_list.profile'),
                ...config('permission_list.catalog'),
                ...config('permission_list.dashboard'),
            ],
            self::Marketing => [
                ...config('permission_list.auth'),
                ...config('permission_list.profile'),
                ...config('permission_list.marketing'),
                ...config('permission_list.dashboard'),
            ],
            self::OrderOps => [
                ...config('permission_list.auth'),
                ...config('permission_list.profile'),
                ...config('permission_list.commerce'),
                ...config('permission_list.tracking'),
                ...config('permission_list.dashboard'),
            ],
            self::Support => [
                ...config('permission_list.auth'),
                ...config('permission_list.profile'),
                'orders list',
                'orders read',
                ...config('permission_list.product_reviews'),
                ...config('permission_list.dashboard'),
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
            self::Manager => 'Gerente da Loja',
            self::CatalogManager => 'Gerente de Catálogo',
            self::Marketing => 'Marketing',
            self::OrderOps => 'Operações de Pedidos',
            self::Support => 'Atendimento',
            self::User => 'User',
        };
    }
}
