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

        $redeemed = DB::transaction(function () use ($code, $tenant, $request) {
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

        if (! $redeemed) {
            return response()->json(['ok' => false, 'message' => 'Invalid or already-used voucher code.'], 422);
        }

        $package = $redeemed->package;
        $router  = $tenant->routers()
            ->when($request->nas, fn ($q) => $q->where('nas_identifier', $request->nas))
            ->first() ?? $tenant->routers()->first();

        $connected = false;

        if ($router && $package) {
            try {
                $mikrotik  = MikrotikService::forRouter($router);
                $connected = $mikrotik->connect();

                if ($connected) {
                    $connected = $mikrotik->createHotspotUser($code, '', $package->mikrotik_profile);
                    $mikrotik->disconnect();
                }
            } catch (\Exception $e) {
                Log::error('MikroTik error during voucher redemption', [
                    'code'  => $code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $connected) {
            Log::warning('Voucher redeemed but MikroTik provisioning failed', ['code' => $code]);
        }

        Transaction::create([
            'tenant_id'    => $tenant->id,
            'router_id'    => $router?->id,
            'package_id'   => $package?->id,
            'phone'        => $request->input('phone'),
            'amount'       => $package?->price ?? 0,
            'status'       => 'completed',
            'voucher_code' => $code,
            'expires_at'   => now()->addHours($package?->duration_hours ?? 24),
            'customer_mac' => $request->input('mac'),
            'customer_ip'  => $request->ip(),
        ]);

        if ($package && $package->price > 0) {
            $this->creditTenantWallet($tenant->id, $package->price);
        }

        return response()->json([
            'ok'       => true,
            'code'     => $code,
            'package'  => $package?->name,
            'duration' => $package?->durationLabel(),
            'message'  => 'Voucher accepted! Connecting you now…',
        ]);
    }

    private function creditTenantWallet(int $tenantId, int $amount): void
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
                    'reference' => 'VCHR-' . ($redeemed->code ?? 'unknown'),
                    'notes'     => $feePct . '% fee on voucher redemption',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to credit tenant wallet for voucher', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
