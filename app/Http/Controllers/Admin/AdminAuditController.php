<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The record of sensitive actions across the platform, newest first.
 */
class AdminAuditController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::with('tenant:id,name')
            ->when($request->filled('isp'), fn ($q) => $q->where('tenant_id', (int) $request->isp))
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->action) . '%'))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit', [
            'logs'    => $logs,
            'tenants' => Tenant::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
