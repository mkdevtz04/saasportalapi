<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'subdomain',
        'logo_path',
        'status',
        'trial_ends_at',
        'monthly_fee_tzs',
        'next_billing_at',
        'last_billed_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at'   => 'datetime',
            'next_billing_at' => 'datetime',
            'last_billed_at'  => 'datetime',
            'monthly_fee_tzs' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function routers(): HasMany
    {
        return $this->hasMany(TenantRouter::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(TenantPackage::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(TenantSettings::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function wallet(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TenantWallet::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
