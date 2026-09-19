<?php

namespace App\Http\Controllers;

use App\Models\TenantRouter;
use App\Services\ProvisioningScript;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Public endpoints a router calls while it is being set up. Each one is protected only by
 * the secret provision token of that router, so the token must never be shown to anyone else.
 */
class RouterProvisionController extends Controller
{
    public function __construct(private ProvisioningScript $scripts)
    {
    }

    /**
     * The setup script the router downloads and imports.
     */
    public function downloadScript(string $token): Response
    {
        $router = TenantRouter::where('provision_token', $token)->first();

        if (! $router) {
            return $this->plain("# ERROR: Invalid or expired provision token.\n:log error \"TrinetPay setup failed: invalid token\"\n", 404);
        }

        try {
            $script = $router->isRadius()
                ? $this->scripts->radiusSetup($router)
                : $this->scripts->apiSetup($router);
        } catch (RuntimeException $e) {
            Log::error('Router setup script could not be built', ['router_id' => $router->id, 'error' => $e->getMessage()]);

            return $this->plain("# ERROR: The platform is not ready to set up routers yet. Contact support.\n:log error \"TrinetPay setup failed: platform not configured\"\n", 503);
        }

        $router->update([
            'provision_status' => 'script_downloaded',
            'provision_note'   => null,
            'last_seen_at'     => now(),
        ]);

        return $this->plain($script, 200, 'trinetpay-bootstrap.rsc');
    }

    /**
     * The hotspot login page, downloaded by the setup script and stored on the router.
     */
    public function loginPage(string $token): Response
    {
        $router = TenantRouter::where('provision_token', $token)->first();

        abort_unless($router && $router->isRadius(), 404);

        return response($this->scripts->loginPage($router), 200, [
            'Content-Type'  => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Called by the router at the end of setup. The failed value lists the steps that did not
     * work, for example ?failed=hotspot,login-page, and is empty when everything went well.
     */
    public function completeProvision(Request $request, string $token): Response
    {
        $router = TenantRouter::where('provision_token', $token)->first();

        if (! $router) {
            return $this->plain("ERROR: Invalid token\n", 404);
        }

        $failed = substr((string) preg_replace('/[^a-z,\-]/', '', strtolower((string) $request->query('failed', ''))), 0, 120);
        $failed = trim($failed, ',');

        if ($failed !== '') {
            $router->update([
                'provision_status' => 'failed',
                'provision_note'   => 'These steps did not work: ' . str_replace(',', ', ', $failed),
                'last_seen_at'     => now(),
                'public_ip'        => $request->ip(),
            ]);

            return $this->plain("PARTIAL: some steps failed ({$failed})\n");
        }

        $router->update([
            'provision_status' => 'completed',
            'provision_note'   => null,
            'provisioned_at'   => now(),
            'status'           => 'online',
            'last_seen_at'     => now(),
            'public_ip'        => $request->ip(),
        ]);

        return $this->plain("OK: Provisioning completed successfully for router {$router->name}\n");
    }

    /**
     * Polled by the setup page to show progress.
     */
    public function checkStatus(string $token): JsonResponse
    {
        $router = TenantRouter::where('provision_token', $token)->first();

        if (! $router) {
            return response()->json(['success' => false, 'message' => 'Router not found'], 404);
        }

        return response()->json([
            'success'        => true,
            'status'         => $router->provision_status ?? 'pending',
            'note'           => $router->provision_note,
            'router_status'  => $router->status,
            'router_name'    => $router->name,
            'nas_identifier' => $router->nas_identifier,
            'provisioned_at' => $router->provisioned_at?->diffForHumans(),
            'last_seen_at'   => $router->last_seen_at?->diffForHumans(),
        ]);
    }

    private function plain(string $body, int $status = 200, ?string $filename = null): Response
    {
        $headers = [
            'Content-Type'  => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ];

        if ($filename) {
            $headers['Content-Disposition'] = 'inline; filename="' . $filename . '"';
        }

        return response($body, $status, $headers);
    }
}
