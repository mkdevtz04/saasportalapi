<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Support\CurrentTenant;

/**
 * Filters every query on the model to the current tenant and stamps new rows with it.
 *
 * This is a safety net, not a replacement for explicit tenant checks in controllers:
 * a forgotten "where tenant_id" can no longer leak another tenant's rows during a
 * tenant request. Use withoutGlobalScopes() when code really must see every tenant.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (empty($model->tenant_id) && CurrentTenant::id() !== null) {
                $model->tenant_id = CurrentTenant::id();
            }
        });
    }
}
