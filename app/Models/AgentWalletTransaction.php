<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentWalletTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'balance_after',
        'reference',
        'description',
        'created_at',
    ];

    protected static function booted(): void
    {
        // Timestamps are off for this table, and the created_at column has no database
        // default, so without this every wallet entry fails on strict databases.
        static::creating(function (AgentWalletTransaction $entry) {
            $entry->created_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(AgentWallet::class);
    }
}
