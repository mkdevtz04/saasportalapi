<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformBillingLog;
use App\Models\Tenant;
use App\Models\WithdrawalRequest;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total'     => Tenant::count(),
            'active'    => Tenant::where('status', 'active')->count(),
            'trial'     => Tenant::where('status', 'trial')->count(),
            'suspended' => Tenant::where('status', 'suspended')->count(),
        ];

        $earnings = [
            'month' => PlatformBillingLog::whereMonth('created_at', now()->month)
                           ->whereYear('created_at', now()->year)
                           ->sum('amount'),
            'total' => PlatformBillingLog::sum('amount'),
        ];

        $pendingWithdrawals = WithdrawalRequest::where('status', 'pending')
            ->selectRaw('count(*) as count, coalesce(sum(amount), 0) as total')
            ->first();

        $recentTenants = Tenant::orderByDesc('created_at')->limit(6)->get();

        $recentBilling = PlatformBillingLog::with('tenant')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact(
            'stats', 'earnings', 'pendingWithdrawals', 'recentTenants', 'recentBilling'
        ));
    }
}
