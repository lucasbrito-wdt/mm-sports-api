<?php

namespace App\Domains\ACL\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $requirement
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $requirement)
    {
        $user = Auth::user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        // Separa a entrada usando o pipe como delimitador
        [$role, $permission] = array_pad(explode('|', $requirement), 2, null);

        if (!empty($role)) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        // Verifica se é uma permissão
        if (!empty($permission)) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized');
    }
}
