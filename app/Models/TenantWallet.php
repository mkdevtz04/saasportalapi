<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class TenantWallet extends Model
{
    protected $fillable = [
        'tenant_id',
        'balance',
        'total_earned',
    ];

    protected function casts(): array
    {
        return [
            'balance'      => 'integer',
            'total_earned' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Credit the wallet. Safe to call concurrently — uses a DB lock.
     */
    public function credit(int $amount): void
    {
        DB::transaction(function () use ($amount) {
            $wallet = self::lockForUpdate()->find($this->id);
            $wallet->balance      += $amount;
            $wallet->total_earned += $amount;
            $wallet->save();

            $this->balance      = $wallet->balance;
            $this->total_earned = $wallet->total_earned;
        });
    }

    /**
     * Refund (rejected withdrawal — balance only, not total_earned).
     */
    public function refund(int $amount): void
    {
        DB::transaction(function () use ($amount) {
            $wallet = self::lockForUpdate()->find($this->id);
            $wallet->balance += $amount;
            $wallet->save();
            $this->balance = $wallet->balance;
        });
    }

    /**
     * Debit (when a withdrawal request is approved). Returns false if insufficient balance.
     */
    public function debit(int $amount): bool
    {
        return DB::transaction(function () use ($amount) {
            $wallet = self::lockForUpdate()->find($this->id);
            if ($wallet->balance < $amount) {
                return false;
            }
            $wallet->balance -= $amount;
            $wallet->save();
            $this->balance = $wallet->balance;
            return true;
        });
    }
}
