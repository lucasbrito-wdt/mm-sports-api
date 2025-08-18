<?php

namespace App\Domains\Shared\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

trait TenantScope
{
    public static function bootTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            // Prevent recursion by checking if we're already in a tenant scope operation
            if (self::isInTenantScopeRecursion()) {
                return;
            }

            self::enterTenantScopeRecursion();

            try {
                $modelClass = $builder->getModel()::class;
                $tenantModels = config('cdf.tenantModels', []);

                // Check if this model should have tenant scope applied
                if (!isset($tenantModels[$modelClass])) {
                    return;
                }

                $modelConfig = $tenantModels[$modelClass];
                if (is_array($modelConfig) && !in_array('list', $modelConfig)) {
                    return;
                } elseif (!is_array($modelConfig) && !$modelConfig) {
                    return;
                }

                // Get user ID directly from session/JWT without triggering Auth facade
                $userId = self::getCurrentUserIdSafely();

                if (!$userId) {
                    return;
                }

                // Check if user is admin using direct database query
                $isAdmin = self::isUserAdminDirectQuery($userId);

                if (!$isAdmin) {
                    // Get tenant_id directly from database
                    $tenantId = self::getUserTenantIdDirectly($userId);

                    if ($tenantId) {
                        $table = $builder->getModel()->getTable();
                        $colunaId = $table === config('cdf.tenantTable') ? "$table.id" : "$table." . config('cdf.tenantColumn');
                        $builder->where($colunaId, $tenantId);
                    }
                }
            } finally {
                self::exitTenantScopeRecursion();
            }
        });

        static::creating(function ($model) {
            if (self::isInTenantScopeRecursion()) {
                return;
            }

            self::enterTenantScopeRecursion();

            try {
                $modelClass = $model::class;
                $tenantModels = config('cdf.tenantModels', []);

                if (!isset($tenantModels[$modelClass]) || $model->getTable() === config('cdf.tenantTable')) {
                    return;
                }

                $userId = self::getCurrentUserIdSafely();

                if ($userId) {
                    $tenantId = self::getUserTenantIdDirectly($userId);
                    if ($tenantId) {
                        $model[config('cdf.tenantColumn')] = $tenantId;
                    }
                }
            } finally {
                self::exitTenantScopeRecursion();
            }
        });
    }

    /**
     * Controle de recursão para evitar loops infinitos
     */
    private static $inTenantScope = false;

    private static function isInTenantScopeRecursion(): bool
    {
        return self::$inTenantScope;
    }

    private static function enterTenantScopeRecursion(): void
    {
        self::$inTenantScope = true;
    }

    private static function exitTenantScopeRecursion(): void
    {
        self::$inTenantScope = false;
    }

    /**
     * Get current user ID without triggering Auth facade loops
     */
    private static function getCurrentUserIdSafely(): ?string
    {
        try {
            // Try to get from session first (most reliable)
            if (session()->has('auth_user_id')) {
                return session('auth_user_id');
            }

            // Try to get user from JWT token if available
            $request = request();
            if ($request && method_exists($request, 'bearerToken') && $request->bearerToken() && class_exists('\Tymon\JWTAuth\Facades\JWTAuth')) {
                try {
                    $token = $request->bearerToken();
                    $payload = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->getPayload();
                    return $payload->get('sub');
                } catch (\Exception $e) {
                    // JWT parsing failed, continue to other methods
                }
            }

            // Fallback: try to get from auth guard without triggering model queries
            if (auth()->check()) {
                return auth()->id();
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if user is admin using direct database query
     */
    private static function isUserAdminDirectQuery(string $userId): bool
    {
        static $adminCache = [];

        if (!isset($adminCache[$userId])) {
            try {
                // Check the correct table structure for roles
                $adminCache[$userId] = DB::table('role_user')
                    ->join('roles', 'role_user.role_id', '=', 'roles.id')
                    ->where('role_user.user_id', $userId)
                    ->where(function ($query) {
                        $query->where('roles.slug', 'admin');
                    })
                    ->exists();
            } catch (\Exception $e) {
                $adminCache[$userId] = false;
            }
        }

        return $adminCache[$userId];
    }

    /**
     * Get user tenant_id directly from database
     */
    private static function getUserTenantIdDirectly(string $userId): ?string
    {
        static $tenantCache = [];

        if (!isset($tenantCache[$userId])) {
            try {
                $user = DB::table('users')
                    ->select('tenant_id', 'id')
                    ->where('id', $userId)
                    ->first();

                $tenantCache[$userId] = $user ? ($user->tenant_id ?? $user->id) : null;
            } catch (\Exception $e) {
                $tenantCache[$userId] = null;
            }
        }

        return $tenantCache[$userId];
    }
}
