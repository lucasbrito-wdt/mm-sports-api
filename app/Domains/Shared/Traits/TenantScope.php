<?php

namespace App\Domains\Shared\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

trait TenantScope
{
    /**
     * Cache da configuração de tenant para evitar múltiplas leituras
     */
    private static array $tenantConfigCache = [];

    public static function bootTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            try {
                static::applyTenantScope($builder);
            } catch (Throwable $e) {
                Log::warning('TenantScope: Erro ao aplicar tenant scope', [
                    'model' => $builder->getModel()::class,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });

        static::creating(function ($model) {
            try {
                static::applyTenantCreating($model);
            } catch (Throwable $e) {
                Log::warning('TenantScope: Erro ao aplicar tenant na criação', [
                    'model' => $model::class,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });
    }

    /**
     * Aplica o scope de tenant na consulta
     */
    private static function applyTenantScope(Builder $builder): void
    {
        $modelClass = $builder->getModel()::class;

        // Verifica se existe usuário autenticado
        if (!static::hasAuthenticatedUser()) {
            return;
        }

        // Verifica se o usuário é admin
        if (static::isAdminUser()) {
            return;
        }

        // Obtém configuração de tenant com cache
        $tenantModels = static::getTenantModelsConfig();

        // Verifica se o modelo está configurado para usar tenant
        if (!static::isModelTenanted($modelClass, $tenantModels)) {
            return;
        }

        // Obtém o ID do tenant
        $tenantId = static::getTenantId();
        if (!$tenantId) {
            Log::warning('TenantScope: Tenant ID não encontrado para usuário', [
                'user_id' => auth()->id(),
                'model' => $modelClass
            ]);
            return;
        }

        // Aplica o filtro de tenant
        $table = $builder->getModel()->getTable();
        $tenantTable = config('cdf.tenantTable', 'tenants');
        $tenantColumn = config('cdf.tenantColumn', 'tenant_id');

        // Verifica se a coluna de tenant existe na tabela
        if (!static::hasColumn($table, $tenantColumn) && $table !== $tenantTable) {
            Log::warning('TenantScope: Coluna de tenant não encontrada', [
                'table' => $table,
                'column' => $tenantColumn,
                'model' => $modelClass
            ]);
            return;
        }

        $colunaId = $table === $tenantTable ? "$table.id" : "$table.$tenantColumn";
        $builder->where($colunaId, $tenantId);

        Log::debug('TenantScope: Filtro aplicado', [
            'model' => $modelClass,
            'tenant_id' => $tenantId,
            'column' => $colunaId
        ]);
    }

    /**
     * Aplica o tenant na criação do modelo
     */
    private static function applyTenantCreating($model): void
    {
        $modelClass = $model::class;

        // Verifica se existe usuário autenticado
        if (!static::hasAuthenticatedUser()) {
            return;
        }

        $tenantTable = config('cdf.tenantTable', 'tenants');
        $tenantColumn = config('cdf.tenantColumn', 'tenant_id');

        // Não aplica tenant na tabela de tenants
        if ($model->getTable() === $tenantTable) {
            return;
        }

        // Obtém configuração de tenant com cache
        $tenantModels = static::getTenantModelsConfig();

        // Verifica se o modelo está configurado para usar tenant
        if (!isset($tenantModels[$modelClass])) {
            return;
        }

        // Verifica se a coluna de tenant existe na tabela
        if (!static::hasColumn($model->getTable(), $tenantColumn)) {
            Log::warning('TenantScope: Coluna de tenant não encontrada na criação', [
                'table' => $model->getTable(),
                'column' => $tenantColumn,
                'model' => $modelClass
            ]);
            return;
        }

        // Obtém o ID do tenant
        $tenantId = static::getTenantId();
        if (!$tenantId) {
            Log::warning('TenantScope: Tenant ID não encontrado na criação', [
                'user_id' => auth()->id(),
                'model' => $modelClass
            ]);
            return;
        }

        $model[$tenantColumn] = $tenantId;

        Log::debug('TenantScope: Tenant aplicado na criação', [
            'model' => $modelClass,
            'tenant_id' => $tenantId,
            'column' => $tenantColumn
        ]);
    }

    /**
     * Verifica se existe usuário autenticado
     */
    private static function hasAuthenticatedUser(): bool
    {
        try {
            return auth()->check() && auth()->user() !== null;
        } catch (Throwable $e) {
            Log::warning('TenantScope: Erro ao verificar autenticação', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Verifica se o usuário é admin
     */
    private static function isAdminUser(): bool
    {
        try {
            $user = auth()->user();
            return $user && $user->hasRole('admin');
        } catch (Throwable $e) {
            Log::warning('TenantScope: Erro ao verificar se usuário é admin', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtém a configuração de modelos tenant com cache
     */
    private static function getTenantModelsConfig(): array
    {
        if (empty(static::$tenantConfigCache)) {
            try {
                static::$tenantConfigCache = config('cdf.tenantModels', []);
            } catch (Throwable $e) {
                Log::warning('TenantScope: Erro ao carregar configuração de tenant', [
                    'error' => $e->getMessage()
                ]);
                static::$tenantConfigCache = [];
            }
        }

        return static::$tenantConfigCache;
    }

    /**
     * Verifica se um modelo está configurado para usar tenant
     */
    private static function isModelTenanted(string $modelClass, array $tenantModels): bool
    {
        if (!isset($tenantModels[$modelClass])) {
            return false;
        }

        $config = $tenantModels[$modelClass];

        // Se for array, verifica se contém 'list'
        if (is_array($config)) {
            return in_array('list', $config);
        }

        // Se for boolean ou outro tipo, usa como tal
        return (bool) $config;
    }

    /**
     * Obtém o ID do tenant do usuário atual
     */
    private static function getTenantId(): ?string
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return null;
            }

            // Prioriza tenant_id se existir, senão usa o próprio ID do usuário
            return $user->tenant_id ?? $user->id;
        } catch (Throwable $e) {
            Log::warning('TenantScope: Erro ao obter tenant ID', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Verifica se uma coluna existe em uma tabela
     */
    private static function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (Throwable $e) {
            Log::warning('TenantScope: Erro ao verificar existência da coluna', [
                'table' => $table,
                'column' => $column,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Limpa o cache de configuração (útil para testes)
     */
    public static function clearTenantConfigCache(): void
    {
        static::$tenantConfigCache = [];
    }
}
