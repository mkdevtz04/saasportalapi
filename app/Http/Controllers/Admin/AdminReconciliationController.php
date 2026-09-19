<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\WalletAuditor;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The page to open before paying any withdrawal. It shows how much money the PalmPesa
 * account should hold, who it is owed to, and flags any wallet the payments cannot explain.
 */
class AdminReconciliationController extends Controller
{
    public function index(WalletAuditor $auditor): View
    {
        $tenants  = $auditor->tenants();
        $platform = $auditor->platform();

        return view('admin.reconciliation', [
            'platform'    => $platform,
            'tenants'     => $tenants,
            'problems'    => $tenants->filter(fn (array $row) => $row['result'] !== 'ok')->count(),
            'shortfall'   => $platform['platform_money'] < 0,
        ]);
    }

    /**
     * Every gateway-confirmed portal payment in a period with its PalmPesa order id, so the list
     * can be checked line by line against the PalmPesa statement.
     */
    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => 'nullable|date_format:Y-m-d',
        ]);

        $zone = 'Africa/Dar_es_Salaam';
        $to   = $request->filled('to') ? Carbon::createFromFormat('Y-m-d', $request->to, $zone)->endOfDay() : now($zone)->endOfDay();
        $from = $request->filled('from') ? Carbon::createFromFormat('Y-m-d', $request->from, $zone)->startOfDay() : $to->copy()->subDays(30)->startOfDay();

        return response()->streamDownload(function () use ($from, $to, $zone) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            Csv::write($out, ['Date (EAT)', 'Tenant', 'Reference', 'PalmPesa order id', 'PalmPesa transaction id', 'Phone', 'Amount (TZS)', 'Status']);

            Transaction::withoutGlobalScopes()
                ->with('tenant:id,name')
                ->where('channel', Transaction::CHANNEL_PORTAL)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($out, $zone) {
                    foreach ($rows as $sale) {
                        Csv::write($out, [
                            $sale->created_at->copy()->timezone($zone)->format('Y-m-d H:i'),
                            $sale->tenant?->name,
                            $sale->reference(),
                            $sale->palmpesa_order_id,
                            $sale->palmpesa_txn_id,
                            $sale->phone,
                            $sale->amount,
                            $sale->status,
                        ]);
                    }
                });

            fclose($out);
        }, 'palmpesa-reconciliation-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
