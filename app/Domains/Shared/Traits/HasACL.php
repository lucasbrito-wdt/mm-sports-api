<?php

namespace App\Domains\Shared\Traits;

use Illuminate\Support\Facades\Request;
use App\Domains\ACL\Services\AccessManagerService;

trait HasACL
{
    public function bootACL(): void
    {
        $route = Request::route();

        if (!$route) return;

        $routeName = $route->getName();      // Ex: "permissionario.index"
        $actionMethod = $route->getActionMethod(); // Ex: "index"

        if (!$routeName || !$actionMethod) return;

        [$subject] = explode('.', $routeName);
        $this->authorized($subject, $actionMethod);
    }

    public function authorized(string $subject, string $action): void
    {
        $acl = $this->getACL();

        if (
            !isset($acl['subject'], $acl['rules']) ||
            $acl['subject'] !== $subject
        ) {
            return;
        }

        $accessService = app(AccessManagerService::class);

        // Caso regras específicas por action
        if (is_array($acl['rules']) && array_key_exists($action, $acl['rules'])) {
            $permissions = (array) $acl['rules'][$action];

            // Converter para formato com espaço: "permission read"
            $permList = array_map(fn($p) => str_replace('.', ' ', $p), $permissions);
            $permString = 'admin|' . implode('|', $permList);

            if (!$accessService->hasAccess($permString)) {
                abort(403, 'Você não tem permissão para acessar este recurso.');
            }
        }

        // Caso regra geral como string
        if (is_string($acl['rules'])) {
            $perm = str_replace('.', ' ', $acl['rules']);

            if (!$accessService->hasAccess($perm)) {
                abort(403, 'Você não tem permissão para acessar este recurso.');
            }
        }
    }
}
