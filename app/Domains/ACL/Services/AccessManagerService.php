<?php

namespace App\Domains\ACL\Services;

use Illuminate\Support\Facades\Auth;

class AccessManagerService
{
    /**
     * Verifica se o usuário tem o papel ou permissão exigida.
     *
     * @param string $requirement Exemplo: "admin|create-post".
     * @return bool
     */
    public function hasAccess(string $requirement): bool
    {
        // Usuário autenticado
        $user = Auth::user();

        // Retorna falso se o usuário não estiver autenticado
        if (!$user) {
            return false;
        }

        // Divide a string de requisitos (role|permission)
        [$role, $permission] = array_pad(explode('|', $requirement), 2, null);

        // Verifica se o usuário tem o papel especificado
        if (!empty($role) && $this->hasRole($user, $role)) {
            return true;
        }

        // Verifica se o usuário tem a permissão especificada
        if (!empty($permission) && $this->hasPermission($user, $permission)) {
            return true;
        }

        return false; // Nenhuma condição foi atendida
    }

    /**
     * Verifica se o usuário tem um papel específico.
     *
     * @param $user
     * @param string $role
     * @return bool
     */
    private function hasRole($user, string $role): bool
    {
        // Implementação: Verifica se o usuário tem o papel
        return method_exists($user, 'hasRole') && $user->hasRole($role);
    }

    /**
     * Verifica se o usuário tem uma permissão específica.
     *
     * @param $user
     * @param string $permission
     * @return bool
     */
    private function hasPermission($user, string $permission): bool
    {
        // Implementação: Verifica se o usuário tem a permissão
        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }
}
