<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\AccessGranter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VoucherController extends Controller
{
    public function redeem(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json(['ok' => false, 'message' => __('portal.portal_not_ready')], 404);
        }

        $request->validate([
            'code'  => 'required|string|max:20',
            'mac'   => 'nullable|string|max:17',
            'ip'    => 'nullable|string|max:45',
            'nas'   => 'nullable|string|max:60',
            'phone' => 'nullable|string|max:20',
        ]);

        $code = strtoupper(trim($request->input('code')));

        // Find and lock the voucher
        $voucher = null;

        try {
            $voucher = DB::transaction(function () use ($code, $tenant, $request) {
                $voucher = Voucher::with('package')
                    ->where('code', $code)
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('used_at')
                    ->lockForUpdate()
                    ->first();

                if (! $voucher) {
                    return null;
                }

                $voucher->update([
                    'used_at'       => now(),
                    'used_by_phone' => $request->input('phone'),
                ]);

                return $voucher;
            });
        } catch (\Exception $e) {
            Log::error('Voucher redemption transaction failed', [
                'code'  => $code,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'message' => __('portal.voucher_error')], 500);
        }

        if (! $voucher) {
            // Not an unused voucher. It may still be a code the customer is entitled to: the one
            // they got after paying, or a voucher they redeemed earlier and are coming back with.
            // Both already have access on the router, so send them straight back in.
            if ($active = $this->activeAccess($tenant->id, $code)) {
                return response()->json([
                    'ok'           => true,
                    'code'         => $code,
                    'package'      => $active->package?->name,
                    'duration'     => $active->package?->durationLabel(),
                    'message'      => __('portal.voucher_active'),
                    'access_ready' => true,
                    'access_ref'   => null,
                ]);
            }

            return response()->json(['ok' => false, 'message' => __('portal.voucher_invalid')], 422);
        }

        $package = $voucher->package;

        if (! $package) {
            return response()->json(['ok' => false, 'message' => __('portal.voucher_no_package')], 422);
        }

        // Find the router
        $router = $tenant->routers()
            ->when($request->nas, fn($q) => $q->where('nas_identifier', $request->nas))
            ->first() ?? $tenant->routers()->first();

        $mikrotikSuccess = false;
        $waitForRouter   = false;

        if ($router && $router->isAgent()) {
            // The router creates the user itself within seconds. The portal waits for that.
            app(AccessGranter::class)->grantViaAgent($router, $package, $code, now()->addHours($package->duration_hours ?? 24));
            $mikrotikSuccess = true;
            $waitForRouter   = true;
        } elseif ($router && $router->isRadius()) {
            // The router connects out to RADIUS, so access is just database rows. It cannot fail
            // because the router is offline, the customer simply logs in when it is back.
            app(AccessGranter::class)->grantViaRadius(
                $router,
                $package,
                $code,
                $code,
                now()->addHours($package->duration_hours ?? 24),
                'voucher:' . $voucher->id,
                $request->input('mac'),
            );
            $mikrotikSuccess = true;
        } elseif ($router) {
            $mikrotikSuccess = app(AccessGranter::class)->grantViaApi($router, $package, $code, $code);
        } else {
            Log::warning('No router found for voucher redemption', ['code' => $code]);
        }

        if (! $mikrotikSuccess) {
            // The customer got no service, so give the voucher back and let them retry.
            Voucher::whereKey($voucher->id)->update(['used_at' => null, 'used_by_phone' => null]);

            return response()->json([
                'ok'      => false,
                'message' => __('portal.voucher_router'),
            ], 503);
        }

        // Record the sale for reporting only. A voucher is cash the tenant already collected
        // offline, so it never credits the tenant wallet and carries no platform fee.
        Transaction::create([
            'tenant_id'    => $tenant->id,
            'router_id'    => $router?->id,
            'package_id'   => $package->id,
            'phone'        => $request->input('phone') ?? '',
            'amount'       => $package->price ?? 0,
            'status'       => 'completed',
            'channel'      => Transaction::CHANNEL_VOUCHER,
            'voucher_code' => $code,
            'expires_at'   => now()->addHours($package->duration_hours ?? 24),
            'customer_mac' => $request->input('mac'),
            'customer_ip'  => $request->ip(),
        ]);

        return response()->json([
            'ok'       => true,
            'code'     => $code,
            'package'  => $package->name,
            'duration' => $package->durationLabel(),
            'message'  => __('portal.voucher_ok'),
            'access_ready' => ! $waitForRouter,
            'access_ref'   => $waitForRouter ? $code : null,
        ]);
    }

    /** Access this ISP has already given for a code and that has not run out yet. */
    private function activeAccess(int $tenantId, string $code): ?Transaction
    {
        return Transaction::with('package')
            ->where('tenant_id', $tenantId)
            ->where('voucher_code', $code)
            ->where('status', 'completed')
            ->where('expires_at', '>', now())
            ->orderByDesc('expires_at')
            ->first();
    }
}
