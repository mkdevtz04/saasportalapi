<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * For uptime monitors. Anyone can ask "is it up", only a caller with the health token gets the
 * details. The status is "down" when the database cannot be reached or the scheduler has
 * stopped, and "degraded" when something needs attention but the site still works.
 */
class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $checks = [];

        $checks['database'] = $this->check(function () {
            DB::select('select 1');

            return ['ok' => true];
        });

        // The scheduler writes a timestamp every minute. Without it payments are not reconciled,
        // routers are not marked offline and nothing is ever cleaned up.
        $beat = Cache::get('scheduler:last_run');
        $checks['scheduler'] = [
            'ok'      => $beat !== null && now()->timestamp - (int) $beat < 300,
            'seconds' => $beat === null ? null : now()->timestamp - (int) $beat,
        ];

        $degraded = [];

        if ($checks['database']['ok']) {
            $checks['queue'] = $this->check(function () {
                $waiting = DB::table('jobs')->count();
                $oldest  = DB::table('jobs')->min('created_at');
                $failed  = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();

                return [
                    'ok'             => $waiting < 200 && $failed === 0 && ($oldest === null || now()->timestamp - (int) $oldest < 600),
                    'waiting'        => $waiting,
                    'failed_last_hr' => $failed,
                ];
            });

            $checks['payments'] = $this->check(function () {
                // A payment still pending after ten minutes should have been settled or failed by the reconciler.
                $stuck = Transaction::withoutGlobalScopes()
                    ->where('channel', Transaction::CHANNEL_PORTAL)
                    ->where('status', 'pending')
                    ->whereNotNull('palmpesa_order_id')
                    ->where('created_at', '<', now()->subMinutes(10))
                    ->where('created_at', '>=', now()->subDay())
                    ->count();

                $noAccess = Transaction::withoutGlobalScopes()
                    ->where('status', 'completed')
                    ->where('provision_status', 'failed')
                    ->where('created_at', '>=', now()->subDay())
                    ->count();

                return ['ok' => $stuck === 0 && $noAccess === 0, 'stuck_pending' => $stuck, 'paid_without_access' => $noAccess];
            });

            foreach (['queue', 'payments'] as $name) {
                if (! $checks[$name]['ok']) {
                    $degraded[] = $name;
                }
            }
        }

        $status = ! $checks['database']['ok'] || ! $checks['scheduler']['ok']
            ? 'down'
            : ($degraded ? 'degraded' : 'ok');

        $body = ['status' => $status];

        $token = (string) config('app.health_token');

        if ($token !== '' && hash_equals($token, (string) $request->header('X-Health-Token'))) {
            $body['checks'] = $checks;
        }

        return response()->json($body, $status === 'down' ? 503 : 200);
    }

    /** @return array<string,mixed> */
    private function check(callable $probe): array
    {
        try {
            return $probe();
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => class_basename($e)];
        }
    }
}
