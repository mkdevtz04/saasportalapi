<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterCommand extends Model
{
    use BelongsToTenant;

    public const REBOOT    = 'reboot';
    public const KICK_USER = 'kick_user';
    public const KICK_ALL  = 'kick_all';
    public const ADD_USER    = 'add_user';
    public const REMOVE_USER = 'remove_user';

    protected $fillable = [
        'tenant_id',
        'router_id',
        'type',
        'reference',
        'payload',
        'status',
        'delivered_at',
        'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(TenantRouter::class, 'router_id');
    }
}
