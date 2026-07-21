<?php

namespace App\Http\Controllers;

use App\Models\PlatformBillingLog;
use App\Models\TenantPackage;
use App\Models\TenantWallet;
use App\Models\Transaction;
use App\Services\MikrotikService;
use App\Services\PalmPesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $tenant   = tenant();
        $packages = $tenant
            ? $tenant->packages()->where('is_active', true)->orderBy('sort_order')->orderBy('price')->get()
            : collect();

        $settings = $tenant?->settings;

        $hotspot = [
            'mac'             => $request->query('mac'),
            'ip'              => $request->query('ip'),
            'username'        => $request->query('username'),
            'link_login_only' => $request->query('link-login-only'),
            'link_orig'       => $request->query('link-orig'),
            'nas'             => $request->query('nas'),
            'error'           => $request->query('error'),
        ];

        $activeVoucher = null;

        if ($tenant && $hotspot['mac']) {
            $activeVoucher = Transaction::where('tenant_id', $tenant->id)
                ->where('customer_mac', $hotspot['mac'])
                ->where('status', 'completed')
                ->where('expires_at', '>', now())
                ->orderByDesc('expires_at')
                ->first();
        }

        return view('portal', compact('tenant', 'packages', 'settings', 'hotspot', 'activeVoucher'));
    }

    public function initiate(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json(['status' => 'error', 'message' => 'Portal not configured.'], 422);
        }

        $request->validate([
            'phone'           => 'required|string|min:9|max:20',
            'package_id'      => 'required|integer',
            'mac'             => 'nullable|string|max:17',
            'ip'              => 'nullable|string|max:45',
            'link_login_only' => 'nullable|string|max:255',
            'link_orig'       => 'nullable|string|max:255',
            'nas'             => 'nullable|string|max:60',
        ]);

        $package = TenantPackage::where('id', $request->package_id)
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->firstOrFail();

        // Identify router by NAS identifier if provided, otherwise first router
        $router = $tenant->routers()
            ->when($request->nas, fn ($q) => $q->where('nas_identifier', $request->nas))
            ->first() ?? $tenant->routers()->first();

        // Create a pending transaction record before calling PalmPesa
        $transaction = Transaction::create([
            'tenant_id'    => $tenant->id,
            'router_id'    => $router?->id,
            'package_id'   => $package->id,
            'phone'        => $request->phone,
            'amount'       => $package->price,
            'status'       => 'pending',
            'customer_mac' => $request->mac,
            'customer_ip'  => $request->ip(),
        ]);

        try {
            // All payments go through the platform's single PalmPesa account
            $result = PalmPesaService::platform()->initiatePayment([
                'name'   => $tenant->name,
                'phone'  => $request->phone,
                'amount' => $package->price,
            ]);

            $transaction->update([
                'palmpesa_txn_id'   => $result['palmpesa_txn_id'],
                'palmpesa_order_id' => $result['order_id'],
            ]);

            // Cache hotspot redirect params for use on payment success
            cache()->put(
                'txn_meta_' . $transaction->id,
                ['link_login_only' => $request->link_login_only, 'link_orig' => $request->link_orig],
                now()->addMinutes(35)
            );

            return response()->json([
                'status'         => 'success',
                'message'        => 'Payment prompt sent to your phone!',
                'transaction_id' => $transaction->id,
                'order_id'       => $result['order_id'],
            ]);
        } catch (\Exception $e) {
            $transaction->update(['status' => 'failed']);
            Log::error('Payment initiation failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function callback(Request $request): JsonResponse
    {
        Log::info('PalmPesa callback received', $request->all());

        $orderId = $request->input('order_id');
        $status  = strtoupper($request->input('payment_status', ''));

        $transaction = Transaction::with(['tenant', 'router', 'package'])
            ->where('palmpesa_order_id', $orderId)
            ->first();

        if (! $transaction) {
            Log::warning('Callback: transaction not found', ['order_id' => $orderId]);
            return response()->json(['status' => 'not_found'], 404);
        }

        if ($transaction->status !== 'pending') {
            return response()->json(['status' => 'already_processed']);
        }

        if ($status === 'COMPLETED') {
            $this->unlockInternet($transaction);
        } else {
            $transaction->update(['status' => 'failed']);
        }

        return response()->json(['status' => 'received']);
    }

    public function checkStatus(Request $request): JsonResponse
    {
        $transactionId = $request->input('transaction_id');
        $orderId       = $request->input('order_id');

        if (! $transactionId) {
            return response()->json(['status' => 'not_found']);
        }

        $transaction = Transaction::with(['package', 'tenant', 'router'])
            ->find($transactionId);

        if (! $transaction) {
            return response()->json(['status' => 'not_found']);
        }

        // If still pending, poll PalmPesa using the platform account
        if ($transaction->isPending() && $orderId) {
            try {
                $result       = PalmPesaService::platform()->checkStatus($orderId);
                $remoteStatus = strtoupper($result['data'][0]['payment_status'] ?? 'PENDING');

                if ($remoteStatus === 'COMPLETED') {
                    $this->unlockInternet($transaction);
                    $transaction->refresh();
                }
            } catch (\Exception $e) {
                Log::error('PalmPesa status check failed', ['error' => $e->getMessage()]);
            }
        }

        if ($transaction->status === 'completed') {
            $meta = cache()->get('txn_meta_' . $transaction->id, []);

            return response()->json([
                'status'     => 'paid',
                'wifi_token' => $transaction->voucher_code,
                'package'    => $transaction->package?->name,
                'login_url'  => $meta['link_login_only'] ?? null,
                'dst'        => $meta['link_orig'] ?? null,
            ]);
        }

        return response()->json(['status' => $transaction->status]);
    }

    private function unlockInternet(Transaction $transaction): void
    {
        $token   = 'TN' . strtoupper(substr(uniqid(), -8));
        $router  = $transaction->router;
        $package = $transaction->package;

        if (! $router || ! $package) {
            Log::error('Cannot unlock: missing router or package', ['transaction_id' => $transaction->id]);
            $transaction->update(['status' => 'completed', 'voucher_code' => $token]);
            $this->creditTenantWallet($transaction);
            return;
        }

        $mikrotik = MikrotikService::forRouter($router);
        $success  = false;

        if ($mikrotik->connect()) {
            $success = $mikrotik->createHotspotUser($token, '', $package->mikrotik_profile);
            $mikrotik->disconnect();
        } else {
            Log::error('MikroTik connection failed', [
                'router_ip'      => $router->router_ip,
                'transaction_id' => $transaction->id,
            ]);
        }

        $transaction->update([
            'status'       => 'completed',
            'voucher_code' => $token,
            'expires_at'   => now()->addHours($package->duration_hours),
        ]);

        // Credit the tenant's platform wallet regardless of MikroTik outcome —
        // the customer paid, so the revenue belongs to the tenant.
        $this->creditTenantWallet($transaction);

        if (! $success) {
            Log::warning('MikroTik user creation failed — token issued, manual provisioning may be needed', [
                'token'          => $token,
                'transaction_id' => $transaction->id,
            ]);
        }
    }

    private function creditTenantWallet(Transaction $transaction): void
    {
        try {
            $feePct      = (float) config('platform.fee_pct', 5);
            $platformFee = (int) round($transaction->amount * $feePct / 100);
            $tenantAmt   = $transaction->amount - $platformFee;

            $wallet = TenantWallet::firstOrCreate(
                ['tenant_id' => $transaction->tenant_id],
                ['balance' => 0, 'total_earned' => 0]
            );
            $wallet->credit($tenantAmt);

            if ($platformFee > 0) {
                PlatformBillingLog::create([
                    'tenant_id' => $transaction->tenant_id,
                    'type'      => 'revenue_share',
                    'amount'    => $platformFee,
                    'reference' => 'TXN-' . $transaction->id,
                    'notes'     => $feePct . '% fee on TZS ' . number_format($transaction->amount),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to credit tenant wallet', [
                'tenant_id'      => $transaction->tenant_id,
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
