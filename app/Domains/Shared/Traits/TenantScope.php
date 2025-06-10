<?php

namespace App\Domains\Shared\Traits;

use Illuminate\Database\Eloquent\Builder;

trait TenantScope
{
    public static function bootTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $modelClass = $builder->getModel()::class;
            $tenantModels = config('cdf.tenantModels');

            if (auth()->check() &&
                ! auth()->user()->hasRole('admin') &&
                isset($tenantModels[$modelClass]) &&
                in_array('list', $tenantModels[$modelClass])
            ) {
                $tenantId = auth()->user()->tenant_id ?? auth()->user()->id;

                $table = $builder->getModel()->getTable();
                $colunaId = $table === config('cdf.tenantTable') ? "$table.id" : "$table." . config('cdf.tenantColumn');
                $builder->where($colunaId, $tenantId);
            }
        });

        static::creating(function ($model) {
            $modelClass = $model::class;
            $tenantModels = config('cdf.tenantModels');
            if (
                auth()->check() &&
                $model->getTable() !== config('cdf.tenantTable') &&
                isset($tenantModels[$modelClass])
            ) {
                $tenantId = auth()->user()->tenant_id ?? auth()->user()->id;

                $model[config('cdf.tenantColumn')] = $tenantId;
            }
        });
    }
}
