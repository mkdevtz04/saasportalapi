<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformBillingLog;
use App\Models\TenantWallet;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\MikrotikService;
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
            return response()->json(['ok' => false, 'message' => 'Portal not found.'], 404);
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

            return response()->json(['ok' => false, 'message' => 'Something went wrong. Please try again.'], 500);
        }

        if (! $voucher) {
            return response()->json(['ok' => false, 'message' => 'Invalid or already-used voucher code.'], 422);
        }

        $package = $voucher->package;

        if (! $package) {
            return response()->json(['ok' => false, 'message' => 'Package not found for this voucher.'], 422);
        }

        // Find the router
        $router = $tenant->routers()
            ->when($request->nas, fn($q) => $q->where('nas_identifier', $request->nas))
            ->first() ?? $tenant->routers()->first();

        $mikrotikSuccess = false;

        if ($router) {
            try {
                $mikrotik = MikrotikService::forRouter($router);

                if ($mikrotik->connect()) {
                    // Create user with code as both username and password
                    $mikrotikSuccess = $mikrotik->createHotspotUser(
                        $code,                      // username
                        $code,                      // password
                        $package->mikrotik_profile  // profile name (must match router)
                    );

                    $mikrotik->disconnect();
                }
            } catch (\Exception $e) {
                Log::error('MikroTik error during voucher redemption', [
                    'code'    => $code,
                    'router'  => $router->id ?? null,
                    'profile' => $package->mikrotik_profile,
                    'error'   => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('No router found for voucher redemption', ['code' => $code]);
        }

        // Record the transaction
        Transaction::create([
            'tenant_id'    => $tenant->id,
            'router_id'    => $router?->id,
            'package_id'   => $package->id,
            'phone'        => $request->input('phone'),
            'amount'       => $package->price ?? 0,
            'status'       => $mikrotikSuccess ? 'completed' : 'completed_with_warning',
            'voucher_code' => $code,
            'expires_at'   => now()->addHours($package->duration_hours ?? 24),
            'customer_mac' => $request->input('mac'),
            'customer_ip'  => $request->ip(),
        ]);

        // Credit the wallet only if price > 0
        if ($package->price > 0) {
            $this->creditTenantWallet($tenant->id, $package->price, $code);
        }

        if (! $mikrotikSuccess) {
            return response()->json([
                'ok'      => false,
                'message' => 'Voucher accepted but failed to connect to the router. Please contact support.',
                'code'    => $code,
            ], 500);
        }

        return response()->json([
            'ok'       => true,
            'code'     => $code,
            'package'  => $package->name,
            'duration' => $package->durationLabel(),
            'message'  => 'Voucher accepted! Connecting you now…',
        ]);
    }


    private function creditTenantWallet(int $tenantId, int $amount, string $voucherCode): void
    {
        try {
            $feePct      = (float) config('platform.fee_pct', 5);
            $platformFee = (int) round($amount * $feePct / 100);
            $tenantAmt   = $amount - $platformFee;

            $wallet = TenantWallet::firstOrCreate(
                ['tenant_id' => $tenantId],
                ['balance' => 0, 'total_earned' => 0]
            );

            $wallet->credit($tenantAmt);

            if ($platformFee > 0) {
                PlatformBillingLog::create([
                    'tenant_id' => $tenantId,
                    'type'      => 'revenue_share',
                    'amount'    => $platformFee,
                    'reference' => 'VCHR-' . $voucherCode,
                    'notes'     => $feePct . '% fee on voucher redemption',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to credit tenant wallet for voucher', [
                'tenant_id' => $tenantId,
                'code'      => $voucherCode,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
