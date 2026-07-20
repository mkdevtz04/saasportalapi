<?php

namespace App\Http\Controllers;

use App\Models\AgentWallet;
use App\Models\TenantWallet;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AgentPosController extends Controller
{
    private function agent()
    {
        return Auth::guard('tenant')->user();
    }

    private function tenant()
    {
        return $this->agent()->tenant;
    }

    public function index(): View
    {
        $agent  = $this->agent();
        $tenant = $this->tenant();

        $packages = $tenant->packages()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->withCount(['vouchers as available' => fn ($q) => $q->whereNull('sold_at')->whereNull('used_at')])
            ->get();

        $wallet = $agent->wallet ?? new AgentWallet(['balance' => 0]);

        $recentSales = Voucher::with('package')
            ->where('agent_id', $agent->id)
            ->whereNotNull('sold_at')
            ->orderByDesc('sold_at')
            ->limit(10)
            ->get();

        return view('pos.index', compact('agent', 'tenant', 'packages', 'wallet', 'recentSales'));
    }

    public function sell(Request $request): JsonResponse
    {
        $agent  = $this->agent();
        $tenant = $this->tenant();

        $validated = $request->validate([
            'package_id'    => 'required|integer',
            'customer_phone'=> 'nullable|string|max:20',
        ]);

        $package = $tenant->packages()
            ->where('id', $validated['package_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $wallet = $agent->wallet;

        if (! $wallet || $wallet->balance < $package->price) {
            return response()->json([
                'ok'      => false,
                'message' => 'Insufficient wallet balance. Ask owner to top up.',
            ], 422);
        }

        // Find and lock an available voucher + debit wallet atomically
        $voucher = DB::transaction(function () use ($agent, $tenant, $package, $wallet, $validated) {
            $voucher = Voucher::where('tenant_id', $tenant->id)
                ->where('package_id', $package->id)
                ->whereNull('sold_at')
                ->whereNull('used_at')
                ->lockForUpdate()
                ->first();

            if (! $voucher) {
                return null;
            }

            $voucher->update([
                'agent_id'       => $agent->id,
                'sold_at'        => now(),
                'used_by_phone'  => $validated['customer_phone'] ?? null,
            ]);

            // Money: agent wallet → tenant wallet
            $debited = $wallet->debit(
                $package->price,
                'SALE-' . $voucher->code,
                'Voucher sale: ' . $package->name
            );

            if (! $debited) {
                throw new \RuntimeException('Wallet debit failed — race condition?');
            }

            $tenantWallet = TenantWallet::firstOrCreate(
                ['tenant_id' => $tenant->id],
                ['balance' => 0, 'total_earned' => 0]
            );
            $tenantWallet->credit($package->price);

            return $voucher;
        });

        if (! $voucher) {
            return response()->json([
                'ok'      => false,
                'message' => 'No vouchers available for this package. Ask owner to generate more.',
            ], 422);
        }

        $wallet->refresh();

        return response()->json([
            'ok'              => true,
            'code'            => $voucher->code,
            'package'         => $package->name,
            'duration'        => $package->durationLabel(),
            'wallet_balance'  => $wallet->balance,
        ]);
    }
}
