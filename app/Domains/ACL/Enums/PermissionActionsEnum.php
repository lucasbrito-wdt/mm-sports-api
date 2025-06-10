<?php

namespace App\Domains\ACL\Enums;

use Exception;

enum PermissionActionsEnum : string
{
    case READ = 'read';
    case CREATE = 'create';
    case DELETE = 'delete';
    case LIST = 'list';
    case EDIT = 'edit';
    case BLOCK = 'block';
    case MANAGE = 'manage';

    /**
     * @throws Exception
     */
    public function label(): string
    {
        return match ($this) {
            PermissionActionsEnum::CREATE => 'Criar',
            PermissionActionsEnum::READ => 'Ler',
            PermissionActionsEnum::DELETE => 'Excluír',
            PermissionActionsEnum::LIST => 'Listar',
            PermissionActionsEnum::EDIT => 'Editar',
            PermissionActionsEnum::BLOCK => 'Bloquear',
            PermissionActionsEnum::MANAGE => 'Gerenciar',
            default => throw new Exception("Unexpected match value: ".json_encode($this)),
        };
    }
}
