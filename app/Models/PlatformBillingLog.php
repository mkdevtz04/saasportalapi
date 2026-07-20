<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformBillingLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'type', 'amount', 'reference', 'notes'];

    protected function casts(): array
    {
        return [
            'amount'     => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
