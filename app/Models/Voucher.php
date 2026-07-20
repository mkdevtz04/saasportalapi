<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    protected $fillable = [
        'tenant_id',
        'package_id',
        'code',
        'batch_ref',
        'used_at',
        'used_by_phone',
        'agent_id',
        'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'sold_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TenantPackage::class, 'package_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'agent_id');
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isSold(): bool
    {
        return $this->sold_at !== null;
    }

    public function isAvailable(): bool
    {
        return $this->sold_at === null && $this->used_at === null;
    }
}
