<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A gateway callback exactly as it arrived, kept so a payment dispute can always be
 * traced back to what the gateway really sent and what we did with it.
 */
class PaymentWebhook extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider',
        'order_id',
        'payload',
        'ip',
        'result',
        'processed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'processed_at' => 'datetime',
            'created_at'   => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentWebhook $hook) {
            $hook->created_at ??= now();
        });
    }
}
