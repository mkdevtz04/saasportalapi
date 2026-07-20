<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AgentWallet extends Model
{
    protected $fillable = [
        'tenant_user_id',
        'balance',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'tenant_user_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(AgentWalletTransaction::class, 'wallet_id');
    }

    // Debit the wallet inside a DB transaction; returns false if insufficient funds
    public function debit(int $amount, string $reference, string $description = ''): bool
    {
        return DB::transaction(function () use ($amount, $reference, $description) {
            $wallet = self::lockForUpdate()->find($this->id);

            if ($wallet->balance < $amount) {
                return false;
            }

            $wallet->balance -= $amount;
            $wallet->save();

            $wallet->walletTransactions()->create([
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reference' => $reference,
                'description' => $description,
            ]);

            $this->balance = $wallet->balance;

            return true;
        });
    }

    public function credit(int $amount, string $reference, string $description = ''): void
    {
        DB::transaction(function () use ($amount, $reference, $description) {
            $wallet = self::lockForUpdate()->find($this->id);
            $wallet->balance += $amount;
            $wallet->save();

            $wallet->walletTransactions()->create([
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reference' => $reference,
                'description' => $description,
            ]);

            $this->balance = $wallet->balance;
        });
    }
}
