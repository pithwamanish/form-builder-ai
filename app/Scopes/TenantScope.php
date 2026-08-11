<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the tenant filter scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = config('app.current_tenant_id', session('tenant_id', 'default'));
        if (!empty($tenantId)) {
            $builder->where($model->getTable() . '.tenant_id', '=', $tenantId);
        }
    }
}
