<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TenantRouter extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'auth_mode',
        'router_ip',
        'username',
        'password',
        'port',
        'nas_identifier',
        'status',
        'last_seen_at',
        'provision_token',
        'agent_token',
        'provision_status',
        'provision_note',
        'offline_alerted_at',
        'identity_ok',
        'provisioned_at',
        'routeros_version',
        'public_ip',
        'active_users',
        'router_uptime',
    ];

    public const MODE_API    = 'api';
    public const MODE_RADIUS = 'radius';
    public const MODE_AGENT  = 'agent';

    protected function casts(): array
    {
        return [
            'last_seen_at'   => 'datetime',
            'offline_alerted_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'port'           => 'integer',
            'active_users'   => 'integer',
            'identity_ok'    => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'router_id');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(RouterCommand::class, 'router_id');
    }

    /** Customers log in through a FreeRADIUS server. */
    public function isRadius(): bool
    {
        return $this->auth_mode === self::MODE_RADIUS;
    }

    /** The router creates customers' hotspot users itself when the platform asks it to. */
    public function isAgent(): bool
    {
        return $this->auth_mode === self::MODE_AGENT;
    }

    /**
     * True when the router connects out to the platform and runs the agent script, which is every
     * mode except the old one where the platform logs in to the router.
     */
    public function runsAgent(): bool
    {
        return $this->isRadius() || $this->isAgent();
    }

    /** How a new router is connected, from ROUTER_DEFAULT_MODE. Anything unknown means agent. */
    public static function defaultMode(): string
    {
        $mode = (string) config('router.default_mode', self::MODE_AGENT);

        return in_array($mode, [self::MODE_AGENT, self::MODE_RADIUS, self::MODE_API], true) ? $mode : self::MODE_AGENT;
    }

    /**
     * The mode a router is switched to, or a new onboarding router gets. Never the old API mode, and
     * never RADIUS while RADIUS is not set up.
     */
    public static function connectMode(): string
    {
        $mode = self::defaultMode();

        if ($mode === self::MODE_RADIUS && ! self::radiusIsConfigured()) {
            return self::MODE_AGENT;
        }

        return $mode === self::MODE_API ? self::MODE_AGENT : $mode;
    }

    /** RADIUS needs a server. Until RADIUS_HOST and RADIUS_SECRET are set it cannot be offered. */
    public static function radiusIsConfigured(): bool
    {
        return (string) config('radius.host') !== '' && (string) config('radius.secret') !== '';
    }

    public function setPasswordAttribute(?string $value): void
    {
        $this->attributes['password'] = $value === null || $value === '' ? null : Crypt::encryptString($value);
    }

    public function getPasswordAttribute(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * The router API user name, always derived from the tenant name so it is safe to put
     * in a script. The tn_ prefix also stops a tenant called "admin" from colliding with
     * the built-in RouterOS admin account.
     */
    public static function apiUsernameFor(Tenant $tenant): string
    {
        $slug = Str::limit(Str::slug($tenant->name, '_'), 24, '');

        return 'tn_' . ($slug !== '' ? $slug : 'isp');
    }

    public static function generateAgentToken(): string
    {
        return 'trinet_agent_' . Str::random(40);
    }

    public function getOrGenerateAgentToken(): string
    {
        if (empty($this->agent_token)) {
            $this->agent_token = static::generateAgentToken();
            $this->save();
        }

        return $this->agent_token;
    }

    /** Queue something for the router to do on its next poll. */
    public function queueCommand(string $type, array $payload = [], ?string $requestedBy = null, ?string $reference = null): RouterCommand
    {
        return $this->commands()->create([
            'tenant_id'    => $this->tenant_id,
            'type'         => $type,
            'reference'    => $reference,
            'payload'      => $payload ?: null,
            'requested_by' => $requestedBy,
        ]);
    }

    /** A router that connects out counts as online while its polls keep arriving. */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes((int) config('radius.offline_after_minutes', 5)));
    }

    public static function generateNasIdentifier(int $tenantId): string
    {
        return 'nas-' . $tenantId . '-' . Str::random(8);
    }

    public static function generateProvisionToken(): string
    {
        return 'trinet_prov_' . Str::random(32);
    }

    public function getOrGenerateProvisionToken(): string
    {
        if (empty($this->provision_token)) {
            $this->provision_token = static::generateProvisionToken();
            $this->save();
        }
        return $this->provision_token;
    }
}
