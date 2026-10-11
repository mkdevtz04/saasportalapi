<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One periodic reading of how much data a router had carried, used to draw usage over time.
 * Written by the agent poll, a few minutes apart, never one row per poll.
 */
class RouterUsageSample extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'router_id',
        'recorded_at',
        'bytes_total',
        'bytes_delta',
        'active_users',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at'  => 'datetime',
            'bytes_total'  => 'integer',
            'bytes_delta'  => 'integer',
            'active_users' => 'integer',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(TenantRouter::class, 'router_id');
    }
}
