<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantPackage extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'price',
        'duration_hours',
        'speed_down_mbps',
        'speed_up_mbps',
        'data_cap_mb',
        'mikrotik_profile',
        'validity_type',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price' => 'integer',
            'duration_hours' => 'integer',
            'speed_down_mbps' => 'integer',
            'speed_up_mbps' => 'integer',
            'data_cap_mb' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'package_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'package_id');
    }

    public function durationLabel(): string
    {
        if ($this->duration_hours < 24) {
            return $this->duration_hours . ' hour' . ($this->duration_hours > 1 ? 's' : '');
        }
        $days = intdiv($this->duration_hours, 24);
        return $days . ' day' . ($days > 1 ? 's' : '');
    }

    public function speedLabel(): string
    {
        return $this->speed_down_mbps . 'Mbps';
    }
}
