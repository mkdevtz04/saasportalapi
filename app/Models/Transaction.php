<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use BelongsToTenant;

    public const CHANNEL_PORTAL  = 'portal';
    public const CHANNEL_VOUCHER = 'voucher';

    protected $fillable = [
        'tenant_id',
        'router_id',
        'package_id',
        'phone',
        'amount',
        'status',
        'provision_status',
        'provision_error',
        'receipt_sent_at',
        'locale',
        'channel',
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
            'receipt_sent_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Every transaction gets an unguessable id for use in public URLs.
        static::creating(function (Transaction $transaction) {
            $transaction->public_id ??= (string) Str::ulid();
        });
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

    /**
     * A short code the customer can quote to support. It is the end of the unguessable public
     * id, so it is unique in practice but reveals nothing that could be used to look the
     * transaction up from outside.
     */
    public function reference(): string
    {
        return strtoupper(substr((string) $this->public_id, -8));
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
