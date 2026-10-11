<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\WithdrawalRequest;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sales for a chosen period, split by type, package, router and day, with a spreadsheet export.
 * Days are East Africa Time. Portal payments and voucher sales are shown separately because
 * only portal payments go into the wallet.
 */
class DashboardReportController extends Controller
{
    private const ZONE = 'Africa/Dar_es_Salaam';

    /** Long ranges load every row, so keep them to a year. */
    private const MAX_DAYS = 366;

    private function tenant()
    {
        return Auth::guard('tenant')->user()->tenant;
    }

    public function index(Request $request): View
    {
        $tenant = $this->tenant();
        [$from, $to] = $this->range($request);

        $sales = $this->sales($tenant->id, $from, $to)
            ->with(['package:id,name', 'router:id,name'])
            ->get(['id', 'package_id', 'router_id', 'channel', 'amount', 'created_at']);

        $byDay = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $byDay[$day->format('Y-m-d')] = ['portal' => 0, 'voucher' => 0];
        }

        $byPackage = [];
        $byRouter  = [];

        foreach ($sales as $sale) {
            $key = $sale->created_at->copy()->timezone(self::ZONE)->format('Y-m-d');

            if (isset($byDay[$key])) {
                $byDay[$key][$sale->channel] += $sale->amount;
            }

            $package = $sale->package?->name ?? 'Deleted package';
            $router  = $sale->router?->name ?? 'Unknown router';

            $byPackage[$package] = $this->add($byPackage[$package] ?? null, $sale->amount);
            $byRouter[$router]   = $this->add($byRouter[$router] ?? null, $sale->amount);
        }

        uasort($byPackage, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        uasort($byRouter, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $totals = [
            'portal'        => (int) $sales->where('channel', Transaction::CHANNEL_PORTAL)->sum('amount'),
            'portal_count'  => $sales->where('channel', Transaction::CHANNEL_PORTAL)->count(),
            'voucher'       => (int) $sales->where('channel', Transaction::CHANNEL_VOUCHER)->sum('amount'),
            'voucher_count' => $sales->where('channel', Transaction::CHANNEL_VOUCHER)->count(),
        ];

        $withdrawals = WithdrawalRequest::where('tenant_id', $tenant->id)
            ->where('status', 'paid')
            ->whereBetween('processed_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as gross, COALESCE(SUM(fee_amount), 0) as fees, COALESCE(SUM(net_amount), 0) as net')
            ->first();

        return view('dashboard.reports', [
            'tenant'      => $tenant,
            'from'        => $from,
            'to'          => $to,
            'byDay'       => $byDay,
            'byPackage'   => $byPackage,
            'byRouter'    => $byRouter,
            'totals'      => $totals,
            'withdrawals' => $withdrawals,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $tenant = $this->tenant();
        [$from, $to] = $this->range($request);

        $filename = 'sales-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($tenant, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // lets Excel read the file as UTF-8

            Csv::write($out, ['Date (EAT)', 'Reference', 'Type', 'Phone', 'Package', 'Router', 'Amount (TZS)', 'Token', 'Expires (EAT)']);

            $this->sales($tenant->id, $from, $to)
                ->with(['package:id,name', 'router:id,name'])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($out) {
                    foreach ($rows as $sale) {
                        Csv::write($out, [
                            $sale->created_at->copy()->timezone(self::ZONE)->format('Y-m-d H:i'),
                            $sale->reference(),
                            $sale->channel === Transaction::CHANNEL_VOUCHER ? 'Voucher' : 'Portal payment',
                            $sale->phone,
                            $sale->package?->name,
                            $sale->router?->name,
                            $sale->amount,
                            $sale->voucher_code,
                            $sale->expires_at?->copy()->timezone(self::ZONE)->format('Y-m-d H:i'),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0:Carbon,1:Carbon} start and end of the chosen days in East Africa Time */
    private function range(Request $request): array
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => 'nullable|date_format:Y-m-d',
        ]);

        $to   = $request->filled('to')
            ? Carbon::createFromFormat('Y-m-d', $request->to, self::ZONE)->endOfDay()
            : now(self::ZONE)->endOfDay();
        $from = $request->filled('from')
            ? Carbon::createFromFormat('Y-m-d', $request->from, self::ZONE)->startOfDay()
            : now(self::ZONE)->startOfMonth();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $from = $to->copy()->subDays(self::MAX_DAYS)->startOfDay();
        }

        return [$from, $to];
    }

    /** Completed sales in the range, all types. */
    private function sales(int $tenantId, Carbon $from, Carbon $to)
    {
        return Transaction::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()]);
    }

    /** @return array{count:int,amount:int} */
    private function add(?array $row, int $amount): array
    {
        return ['count' => ($row['count'] ?? 0) + 1, 'amount' => ($row['amount'] ?? 0) + $amount];
    }
}
