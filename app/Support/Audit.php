<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records who did what. The actor is read from whoever is logged in: the platform admin,
 * a tenant user, or "system" for scheduled jobs. If the platform admin is acting as a
 * tenant, both are recorded so support actions can never pass as the owner's own.
 *
 * Writing the record must never stop the action it describes, so a failure is only logged.
 */
class Audit
{
    /**
     * @param array<string,mixed> $meta  facts about the action. Never put passwords or full phone numbers here.
     */
    public static function record(string $action, ?int $tenantId = null, array $meta = [], ?Model $subject = null): void
    {
        try {
            [$type, $id, $label] = self::actor();

            $impersonator = session('impersonated_by');

            if ($impersonator) {
                $meta['impersonated_by_admin'] = $impersonator;
            }

            AuditLog::create([
                'actor_type'   => $type,
                'actor_id'     => $id,
                'actor_label'  => $label,
                'tenant_id'    => $tenantId,
                'action'       => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'meta'         => $meta ?: null,
                'ip'           => request()->ip(),
            ]);
        } catch (Throwable $e) {
            Log::error('Could not write an audit record', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }

    /** Hide the middle of a phone number so a record can name it without exposing it. */
    public static function maskPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';

        return strlen($digits) >= 6 ? substr($digits, 0, 3) . str_repeat('*', strlen($digits) - 5) . substr($digits, -2) : null;
    }

    /** @return array{0:string,1:?int,2:?string} */
    private static function actor(): array
    {
        if (Auth::guard('admin')->check()) {
            $admin = Auth::guard('admin')->user();

            return ['admin', $admin->id, $admin->email];
        }

        if (Auth::guard('tenant')->check()) {
            $user = Auth::guard('tenant')->user();

            return ['tenant', $user->id, $user->email];
        }

        return ['system', null, null];
    }
}
