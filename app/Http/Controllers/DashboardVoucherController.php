<?php

namespace App\Http\Controllers;

use App\Models\TenantPackage;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardVoucherController extends Controller
{
    private function tenant()
    {
        return Auth::guard('tenant')->user()->tenant;
    }

    public function index(): View
    {
        $tenant = $this->tenant();

        // Group by batch_ref with stats
        $batches = Voucher::select('batch_ref', 'package_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN used_at IS NOT NULL THEN 1 ELSE 0 END) as used_count'),
                DB::raw('SUM(CASE WHEN sold_at IS NOT NULL AND used_at IS NULL THEN 1 ELSE 0 END) as sold_count'),
                DB::raw('MIN(created_at) as created_at')
            )
            ->where('tenant_id', $tenant->id)
            ->groupBy('batch_ref', 'package_id')
            ->orderByDesc('created_at')
            ->with('package')
            ->paginate(15);

        return view('dashboard.vouchers.index', compact('tenant', 'batches'));
    }

    public function generate(): View
    {
        $tenant   = $this->tenant();
        $packages = $tenant->packages()->where('is_active', true)->orderBy('sort_order')->get();
        return view('dashboard.vouchers.generate', compact('tenant', 'packages'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->tenant();

        $validated = $request->validate([
            'package_id' => 'required|integer',
            'quantity'   => 'required|integer|min:1|max:500',
        ]);

        $package = TenantPackage::where('id', $validated['package_id'])
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->firstOrFail();

        $qty      = (int) $validated['quantity'];
        $batchRef = 'BTCH' . strtoupper(Str::random(8));

        // Generate unique codes
        $codes = collect();
        $attempts = 0;
        while ($codes->count() < $qty && $attempts < $qty * 3) {
            $code = 'TN' . strtoupper(Str::random(8));
            if (! $codes->contains($code)) {
                $codes->push($code);
            }
            $attempts++;
        }

        // Remove any that somehow already exist globally
        $existing = Voucher::whereIn('code', $codes)->pluck('code');
        $codes    = $codes->diff($existing)->take($qty)->values();

        if ($codes->count() < $qty) {
            return back()->withErrors(['quantity' => 'Could not generate enough unique codes. Please try again.']);
        }

        $now  = now()->toDateTimeString();
        $rows = $codes->map(fn ($code) => [
            'tenant_id'  => $tenant->id,
            'package_id' => $package->id,
            'code'       => $code,
            'batch_ref'  => $batchRef,
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        try {
            Voucher::insert($rows);
        } catch (\Illuminate\Database\QueryException) {
            return back()->withErrors(['quantity' => 'Code collision detected. Please try again.']);
        }

        return redirect()->route('dashboard.vouchers.print', $batchRef)
            ->with('success', $qty . ' vouchers generated for "' . $package->name . '".');
    }

    public function print(string $batchRef): View
    {
        $tenant   = $this->tenant();
        $vouchers = Voucher::with('package')
            ->where('tenant_id', $tenant->id)
            ->where('batch_ref', $batchRef)
            ->get();

        abort_if($vouchers->isEmpty(), 404);

        $package = $vouchers->first()->package;
        $settings = $tenant->settings;

        return view('dashboard.vouchers.print', compact('tenant', 'vouchers', 'package', 'batchRef', 'settings'));
    }

    public function destroyBatch(string $batchRef): RedirectResponse
    {
        $tenant = $this->tenant();

        $deleted = Voucher::where('tenant_id', $tenant->id)
            ->where('batch_ref', $batchRef)
            ->whereNull('used_at')
            ->whereNull('sold_at')
            ->delete();

        return redirect()->route('dashboard.vouchers.index')
            ->with('success', "{$deleted} unused vouchers from batch deleted.");
    }
}
