<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One movement of tenant money. Rows are never edited or deleted. A mistake is
 * corrected by adding a new "adjustment" entry.
 */
class WalletEntry extends Model
{
    use BelongsToTenant;

    public const PAYMENT           = 'payment';
    public const WITHDRAWAL        = 'withdrawal';
    public const WITHDRAWAL_REFUND = 'withdrawal_refund';
    public const OPENING_BALANCE   = 'opening_balance';
    public const ADJUSTMENT        = 'adjustment';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'type',
        'amount',
        'balance_after',
        'reference',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'integer',
            'balance_after' => 'integer',
            'meta'          => 'array',
            'created_at'    => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WalletEntry $entry) {
            $entry->created_at ??= now();
        });

        static::updating(function () {
            throw new LogicException('Wallet entries are append-only and cannot be edited.');
        });

        static::deleting(function () {
            throw new LogicException('Wallet entries are append-only and cannot be deleted.');
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
