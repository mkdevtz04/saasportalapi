<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        return response()->json([
            'ok'       => true,
            'code'     => $code,
            'package'  => $package?->name,
            'duration' => $package?->durationLabel(),
            'message'  => 'Voucher accepted! Connecting you now…',
        ]);
    }
}
