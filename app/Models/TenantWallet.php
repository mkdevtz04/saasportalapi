<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Money the platform owes a tenant. The balance is a cached total of the ledger in
 * wallet_entries, and every change goes through move(), which writes the ledger entry
 * and updates the balance together under a row lock.
 */
class TenantWallet extends Model
{
    use BelongsToTenant;

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

    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class, 'tenant_id', 'tenant_id');
    }

    /**
     * Add money. The same type and reference is only ever applied once, so a retried
     * callback or a double click cannot pay a tenant twice. Returns false if it was
     * already applied.
     */
    public function credit(int $amount, string $type, string $reference, array $meta = []): bool
    {
        return $this->move($amount, $type, $reference, $meta);
    }

    /**
     * Take money out. Returns false if the balance is too low or the movement was
     * already applied.
     */
    public function debit(int $amount, string $type, string $reference, array $meta = []): bool
    {
        return $this->move(-$amount, $type, $reference, $meta);
    }

    /**
     * Give money back after a rejected withdrawal. Does not count as earnings.
     */
    public function refund(int $amount, string $reference, array $meta = []): bool
    {
        return $this->move($amount, WalletEntry::WITHDRAWAL_REFUND, $reference, $meta);
    }

    private function move(int $signedAmount, string $type, string $reference, array $meta): bool
    {
        return DB::transaction(function () use ($signedAmount, $type, $reference, $meta) {
            $wallet = self::withoutGlobalScopes()->lockForUpdate()->findOrFail($this->id);

            $alreadyApplied = WalletEntry::withoutGlobalScopes()
                ->where('tenant_id', $wallet->tenant_id)
                ->where('type', $type)
                ->where('reference', $reference)
                ->exists();

            if ($alreadyApplied || $wallet->balance + $signedAmount < 0) {
                $this->syncFrom($wallet);

                return false;
            }

            $wallet->balance += $signedAmount;

            if ($type === WalletEntry::PAYMENT) {
                $wallet->total_earned += $signedAmount;
            }

            $wallet->save();

            WalletEntry::create([
                'tenant_id'     => $wallet->tenant_id,
                'type'          => $type,
                'amount'        => $signedAmount,
                'balance_after' => $wallet->balance,
                'reference'     => $reference,
                'meta'          => $meta ?: null,
            ]);

            $this->syncFrom($wallet);

            return true;
        });
    }

    private function syncFrom(TenantWallet $fresh): void
    {
        $this->balance      = $fresh->balance;
        $this->total_earned = $fresh->total_earned;
    }
}
