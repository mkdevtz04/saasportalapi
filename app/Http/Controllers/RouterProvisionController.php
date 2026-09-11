<?php

namespace App\Http\Controllers;

use App\Models\TenantRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class RouterProvisionController extends Controller
{
    /**
     * Serves the dynamic RouterOS script (.rsc) when MikroTik executes /tool fetch
     */
    public function downloadScript(Request $request, string $token): Response
    {
        $router = TenantRouter::where('provision_token', $token)->first();

        if (! $router) {
            $errorScript = "# ERROR: Invalid or expired provision token.\n"
                . ":log error \"WifiKitaa Provisioning failed: Invalid Token\"\n";
            return response($errorScript, 404, ['Content-Type' => 'text/plain']);
        }

        // Mark status as script_downloaded
        $router->update([
            'provision_status' => 'script_downloaded',
            'last_seen_at'     => now(),
        ]);

        $tenant       = $router->tenant;
        $host         = $request->getHost();
        $scheme       = $request->getScheme();
        $subdomain    = $tenant ? $tenant->subdomain : 'app';
        $nowFormatted = now()->toDateTimeString();

        $completeUrl  = "{$scheme}://{$host}/provision/{$token}/complete";
        $portalDomain = "{$subdomain}." . preg_replace('/^www\./', '', $host);
        $mainDomain   = preg_replace('/^www\./', '', $host);

        // Build RouterOS script
        $script = <<<ROUTEROS
# =========================================================
# WifiKitaa Automated MikroTik Provisioning Script
# Tenant: {$subdomain}
# Router: {$router->name} ({$router->nas_identifier})
# Generated at: {$nowFormatted}
# =========================================================

:log info "WifiKitaa Provisioning: Starting setup..."

# 1. Save system backup before changes
/system backup save name="trinetpay-backup"

# 2. Enable API Service
/ip service enable api
/ip service set api port={$router->port}

# 3. Configure API User Credentials
:if ([:len [/user find name="{$router->username}"]] = 0) do={
    /user add name="{$router->username}" password="{$router->password}" group=full comment="WifiKitaa API User"
} else={
    /user set [find name="{$router->username}"] password="{$router->password}" group=full
}

# 4. Configure Hotspot Walled Garden Rules
/ip hotspot walled-garden
:if ([:len [find dst-host="{$portalDomain}"]] = 0) do={
    add dst-host="{$portalDomain}" comment="WifiKitaa Portal"
}
:if ([:len [find dst-host="{$mainDomain}"]] = 0) do={
    add dst-host="{$mainDomain}" comment="WifiKitaa Main Site"
}
:if ([:len [find dst-host="palmpesa.drmlelwa.co.tz"]] = 0) do={
    add dst-host="palmpesa.drmlelwa.co.tz" comment="PalmPesa Gateway"
}

# 5. Configure Hotspot Profile Redirect Settings
/ip hotspot profile
:foreach p in=[find] do={
    set \$p login-by=http-chap html-directory-override=""
}

:log info "WifiKitaa Provisioning: Setup complete! Sending pingback to server..."

# 6. Signal Completion Back to WifiKitaa Server
/tool fetch url="{$completeUrl}" keep-result=no check-certificate=no

:log info "WifiKitaa Provisioning: Successfully registered with WifiKitaa!"
ROUTEROS;

        return response($script, 200, [
            'Content-Type'        => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'inline; filename="trinetpay-bootstrap.rsc"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Callback hit by MikroTik fetch at the end of setup
     */
    public function completeProvision(string $token): Response
    {
        $router = TenantRouter::where('provision_token', $token)->first();

        if ($router) {
            $router->update([
                'provision_status' => 'completed',
                'provisioned_at'   => now(),
                'status'           => 'online',
                'last_seen_at'     => now(),
            ]);

            return response("OK: Provisioning completed successfully for router {$router->name}\n", 200, [
                'Content-Type' => 'text/plain',
            ]);
        }

        return response("ERROR: Invalid token\n", 404, ['Content-Type' => 'text/plain']);
    }

    /**
     * AJAX endpoint called by frontend status polling loop
     */
    public function checkStatus(string $token): JsonResponse
    {
        $router = TenantRouter::where('provision_token', $token)->first();

        if (! $router) {
            return response()->json([
                'success' => false,
                'message' => 'Router not found',
            ], 404);
        }

        return response()->json([
            'success'          => true,
            'status'           => $router->provision_status ?? 'pending',
            'router_status'    => $router->status,
            'router_name'      => $router->name,
            'nas_identifier'   => $router->nas_identifier,
            'provisioned_at'   => $router->provisioned_at ? $router->provisioned_at->diffForHumans() : null,
            'last_seen_at'     => $router->last_seen_at ? $router->last_seen_at->diffForHumans() : null,
        ]);
    }
}
