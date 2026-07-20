<?php

namespace App\Http\Controllers;

use App\Models\TenantWallet;
use App\Models\Transaction;
use App\Models\WithdrawalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        return view('dashboard.home', compact(
            'tenant', 'todayRevenue', 'monthRevenue', 'monthCount',
            'wallet', 'chartDays', 'chartRevenue', 'recentTransactions', 'routerStats'
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

        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
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
            'withdrawal_number' => 'nullable|string|max:30',
            'logo'              => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        $data = [
            'brand_color'       => $validated['brand_color'] ?? '#0066cc',
            'tagline'           => $validated['tagline'] ?? null,
            'contact_phone'     => $validated['contact_phone'] ?? null,
            'withdrawal_number' => $validated['withdrawal_number'] ?? null,
        ];

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store("logos/{$tenant->id}", 'public');
            $data['custom_logo_path'] = $path;
        }

        $tenant->settings()->updateOrCreate(['tenant_id' => $tenant->id], $data);

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

        $debited = $wallet->debit($validated['amount']);

        if (! $debited) {
            return back()->withErrors(['amount' => 'Insufficient wallet balance.'])->withInput();
        }

        WithdrawalRequest::create([
            'tenant_id'     => $tenant->id,
            'amount'        => $validated['amount'],
            'mobile_number' => $validated['mobile_number'],
            'status'        => 'pending',
        ]);

        return back()->with('success', 'Withdrawal request submitted. Processing within 24 hours.');
    }
}
