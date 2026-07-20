<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSettings extends Model
{
    protected $fillable = [
        'tenant_id',
        'brand_color',
        'tagline',
        'custom_logo_path',
        'contact_phone',
        'withdrawal_number',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
