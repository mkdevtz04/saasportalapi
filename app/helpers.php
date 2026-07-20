<?php

use App\Models\Tenant;

if (! function_exists('tenant')) {
    /**
     * Return the currently resolved tenant for this request, or null on the main domain.
     */
    function tenant(): ?Tenant
    {
        return app()->bound('tenant') ? app('tenant') : null;
    }
}
