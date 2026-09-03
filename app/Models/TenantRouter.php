<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TenantRouter extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'router_ip',
        'username',
        'password',
        'port',
        'nas_identifier',
        'status',
        'last_seen_at',
        'provision_token',
        'provision_status',
        'provisioned_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at'   => 'datetime',
            'provisioned_at' => 'datetime',
            'port'           => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'router_id');
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    public function getPasswordAttribute(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return '';
        }
    }

    public static function generateNasIdentifier(int $tenantId): string
    {
        return 'nas-' . $tenantId . '-' . Str::random(8);
    }

    public static function generateProvisionToken(): string
    {
        return 'trinet_prov_' . Str::random(32);
    }

    public function getOrGenerateProvisionToken(): string
    {
        if (empty($this->provision_token)) {
            $this->provision_token = static::generateProvisionToken();
            $this->save();
        }
        return $this->provision_token;
    }
}
