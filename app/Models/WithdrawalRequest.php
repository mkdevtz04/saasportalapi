<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'amount',
        'fee_amount',
        'net_amount',
        'mobile_number',
        'status',
        'admin_notes',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'integer',
            'fee_amount'   => 'integer',
            'net_amount'   => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Platform fee kept from a withdrawal of this gross amount, in whole TZS.
     */
    public static function feeFor(int $amount): int
    {
        $pct = max(0.0, (float) config('platform.withdrawal_fee_pct', 0));

        return (int) round($amount * $pct / 100);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isPaid(): bool     { return $this->status === 'paid'; }
}
