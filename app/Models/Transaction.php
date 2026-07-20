<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'tenant_id',
        'router_id',
        'package_id',
        'phone',
        'amount',
        'status',
        'palmpesa_order_id',
        'palmpesa_txn_id',
        'voucher_code',
        'expires_at',
        'customer_mac',
        'customer_ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(TenantRouter::class, 'router_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TenantPackage::class, 'package_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
