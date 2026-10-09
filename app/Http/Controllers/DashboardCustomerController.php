<?php

namespace App\Http\Controllers;

use App\Services\CustomerDirectory;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Who an ISP's customers are: their phone numbers, their devices and everything they bought.
 *
 * The platform keeps sales, not customers, so this is the one place those sales are read as
 * people. It answers the question an ISP is actually asked all day — "I paid, where is my
 * internet?" — from a phone number or a MAC address.
 */
class DashboardCustomerController extends Controller
{
    public function __construct(private CustomerDirectory $directory)
    {
    }

    private function tenant()
    {
        return Auth::guard('tenant')->user()->tenant;
    }

    public function index(Request $request): View
    {
        $tenant = $this->tenant();

        $filters = [
            'search' => $request->string('search')->trim()->limit(60, '')->value(),
            'status' => in_array($request->query('status'), ['active', 'expired'], true) ? $request->query('status') : null,
            'router' => $request->integer('router') ?: null,
        ];

        return view('dashboard.customers.index', [
            'tenant'    => $tenant,
            'customers' => $this->directory->list($tenant->id, $filters),
            'summary'   => $this->directory->summary($tenant->id),
            'routers'   => $tenant->routers()->orderBy('name')->get(['id', 'name']),
            'filters'   => $filters,
        ]);
    }

    public function show(Request $request): View
    {
        $tenant   = $this->tenant();
        $identity = (string) $request->query('id', '');

        // The lookup is scoped to this ISP, so a phone number belonging to another ISP's customer
        // is simply not found here.
        $customer = $identity !== '' ? $this->directory->profile($tenant->id, $identity) : null;

        abort_if($customer === null, 404);

        return view('dashboard.customers.show', [
            'tenant'   => $tenant,
            'customer' => $customer,
        ]);
    }

    /**
     * The whole directory as a spreadsheet. An ISP asks for this to text their customers about
     * an outage, or to work out who to win back.
     */
    public function export(Request $request): StreamedResponse
    {
        $tenant = $this->tenant();

        $filters = [
            'search' => $request->string('search')->trim()->limit(60, '')->value(),
            'status' => in_array($request->query('status'), ['active', 'expired'], true) ? $request->query('status') : null,
            'router' => $request->integer('router') ?: null,
        ];

        $filename = 'customers-' . now('Africa/Dar_es_Salaam')->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($tenant, $filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // lets Excel read the file as UTF-8

            Csv::write($out, [
                'Phone', 'Device MAC', 'Last IP', 'Devices', 'Purchases',
                'Spent (TZS)', 'First seen (EAT)', 'Last seen (EAT)', 'Access until (EAT)', 'Status',
            ]);

            $page = 1;

            do {
                $customers = $this->directory->list($tenant->id, $filters, $page);

                foreach ($customers as $customer) {
                    Csv::write($out, [
                        $customer->phone,
                        $customer->mac,
                        $customer->ip,
                        $customer->devices,
                        $customer->purchases,
                        $customer->spent,
                        $customer->first_seen?->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i'),
                        $customer->last_seen?->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i'),
                        $customer->expires_at?->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i'),
                        $customer->active ? 'Active' : 'Expired',
                    ]);
                }

                $page++;
            } while ($customers->hasMorePages());

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
