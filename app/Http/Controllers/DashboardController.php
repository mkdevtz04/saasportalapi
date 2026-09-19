<?php

namespace App\Http\Controllers;

use App\Models\TenantWallet;
use App\Models\Transaction;
use App\Models\WalletEntry;
use App\Models\WithdrawalRequest;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private function tenant()
    {
        return Auth::guard('tenant')->user()->tenant;
    }

    public function index(): View
    {
        $tenant = $this->tenant();

        $todayRevenue = Transaction::where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->sum('amount');

        $monthRevenue = Transaction::where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $monthCount = Transaction::where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $wallet = $tenant->wallet;

        // 7-day chart data
        $chartDays    = [];
        $chartRevenue = [];
        for ($i = 6; $i >= 0; $i--) {
            $date           = now()->subDays($i);
            $chartDays[]    = $date->format('D d/m');
            $chartRevenue[] = (int) Transaction::where('tenant_id', $tenant->id)
                ->where('status', 'completed')
                ->whereDate('created_at', $date)
                ->sum('amount');
        }

        $recentTransactions = Transaction::with('package')
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->limit(6)
            ->get();

        $routerStats = $tenant->routers()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Things the owner should act on: routers that stopped reporting, and customers who paid
        // but never got online.
        $offlineRouters = $tenant->routers()->where('auth_mode', 'radius')->get()
            ->filter(fn ($router) => $router->last_seen_at !== null && ! $router->isOnline());

        $renamedRouters = $tenant->routers()->where('auth_mode', 'radius')->where('identity_ok', false)->get();

        $accessProblems = Transaction::where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->where('provision_status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return view('dashboard.home', compact(
            'tenant', 'todayRevenue', 'monthRevenue', 'monthCount',
            'wallet', 'chartDays', 'chartRevenue', 'recentTransactions', 'routerStats', 'offlineRouters', 'renamedRouters', 'accessProblems'
        ));
    }

    public function transactions(Request $request): View
    {
        $tenant = $this->tenant();

        $query = Transaction::with('package')
            ->where('tenant_id', $tenant->id)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->query('access') === 'failed') {
            $query->where('status', 'completed')->where('provision_status', 'failed');
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // The reference a customer reads out is the end of the public id.
        if ($request->filled('ref')) {
            $query->where('public_id', 'like', '%' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $request->ref)));
        }

        if (in_array($request->channel, [Transaction::CHANNEL_PORTAL, Transaction::CHANNEL_VOUCHER], true)) {
            $query->where('channel', $request->channel);
        }

        $transactions = $query->paginate(20)->withQueryString();

        return view('dashboard.transactions', compact('tenant', 'transactions'));
    }

    public function settings(): View
    {
        $tenant   = $this->tenant();
        $settings = $tenant->settings;
        return view('dashboard.settings', compact('tenant', 'settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $tenant = $this->tenant();

        $validated = $request->validate([
            'brand_color'       => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tagline'           => 'nullable|string|max:200',
            'contact_phone'     => 'nullable|string|max:30',
            'default_language'  => 'required|in:sw,en',
            'withdrawal_number' => 'nullable|string|max:30',
            'logo'              => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        $data = [
            'brand_color'       => $validated['brand_color'] ?? '#0066cc',
            'tagline'           => $validated['tagline'] ?? null,
            'contact_phone'     => $validated['contact_phone'] ?? null,
            'default_language'  => $validated['default_language'],
            'withdrawal_number' => $validated['withdrawal_number'] ?? null,
        ];

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store("logos/{$tenant->id}", 'public');
            $data['custom_logo_path'] = $path;
        }

        $previousNumber = $tenant->settings()->value('withdrawal_number');

        $tenant->settings()->updateOrCreate(['tenant_id' => $tenant->id], $data);

        // The payout number is what an attacker would change first, so every change is recorded.
        if (($data['withdrawal_number'] ?? null) !== $previousNumber) {
            Audit::record('settings.payout_number_changed', $tenant->id, [
                'from' => Audit::maskPhone($previousNumber),
                'to'   => Audit::maskPhone($data['withdrawal_number'] ?? null),
            ]);
        }

        return back()->with('success', 'Settings saved successfully.');
    }

    public function wallet(): View
    {
        $tenant   = $this->tenant();
        $wallet   = $tenant->wallet ?? new TenantWallet(['balance' => 0, 'total_earned' => 0]);
        $settings = $tenant->settings;

        $requests = WithdrawalRequest::where('tenant_id', $tenant->id)
            ->latest()
            ->paginate(10);

        return view('dashboard.wallet', compact('tenant', 'wallet', 'settings', 'requests'));
    }

    public function requestWithdrawal(Request $request): RedirectResponse
    {
        $tenant = $this->tenant();

        $validated = $request->validate([
            'amount'        => 'required|integer|min:5000',
            'mobile_number' => 'required|string|max:30',
        ]);

        $wallet = $tenant->wallet ?? TenantWallet::firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['balance' => 0, 'total_earned' => 0]
        );

        if ($wallet->balance < $validated['amount']) {
            return back()->withErrors(['amount' => 'Insufficient wallet balance.'])->withInput();
        }

        $amount = (int) $validated['amount'];
        $fee    = WithdrawalRequest::feeFor($amount);
        $net    = $amount - $fee;

        try {
            DB::transaction(function () use ($tenant, $validated, $wallet, $amount, $fee, $net) {
                $withdrawal = WithdrawalRequest::create([
                    'tenant_id'     => $tenant->id,
                    'amount'        => $amount,
                    'fee_amount'    => $fee,
                    'net_amount'    => $net,
                    'mobile_number' => $validated['mobile_number'],
                    'status'        => 'pending',
                ]);

                // The debit re-checks the balance under a row lock, so two requests
                // sent at the same moment cannot both spend the same money.
                $debited = $wallet->debit($amount, WalletEntry::WITHDRAWAL, 'WDR-' . $withdrawal->id, [
                    'fee' => $fee,
                    'net' => $net,
                ]);

                if (! $debited) {
                    throw new \RuntimeException('Insufficient balance during transaction.');
                }

                Audit::record('withdrawal.requested', $tenant->id, [
                    'amount' => $amount,
                    'fee'    => $fee,
                    'net'    => $net,
                    'to'     => Audit::maskPhone($validated['mobile_number']),
                ], $withdrawal);
            });
        } catch (\Exception $e) {
            return back()->withErrors(['amount' => 'Withdrawal failed: ' . $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Withdrawal request submitted. You will receive TZS ' . number_format($net) . ' after a TZS ' . number_format($fee) . ' fee. Processing within 24 hours.');
    }
}
