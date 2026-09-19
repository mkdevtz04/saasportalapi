<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One entry in the record of sensitive actions. Never edited, never deleted.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'actor_label',
        'tenant_id',
        'action',
        'subject_type',
        'subject_id',
        'meta',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta'       => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            $log->created_at ??= now();
        });

        static::updating(function () {
            throw new LogicException('Audit log entries cannot be edited.');
        });

        static::deleting(function () {
            throw new LogicException('Audit log entries cannot be deleted.');
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
