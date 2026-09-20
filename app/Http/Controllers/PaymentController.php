<?php

namespace App\Http\Controllers;

use App\Models\PaymentWebhook;
use App\Models\TenantPackage;
use App\Models\Transaction;
use App\Services\AgentAccess;
use App\Services\PalmPesaService;
use App\Services\PaymentSettlement;
use App\Support\HotspotUrl;
use App\Support\Phone;
use App\Support\TenantUrls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private PaymentSettlement $settlement)
    {
    }

    public function index(Request $request): View
    {
        $tenant = tenant();

        // No ISP behind the link means no packages to sell, no wallet to credit and no router to
        // open. Saying so is the only honest answer; a blank portal would just take a payment
        // nobody could be paid for.
        if (! $tenant) {
            return view('portal-unlinked', ['locale' => app()->getLocale()]);
        }

        $packages = $tenant->packages()->where('is_active', true)->orderBy('sort_order')->orderBy('price')->get();

        $settings = $tenant->settings;

        $hotspot = [
            'mac'             => $this->clean($request->query('mac'), 17),
            'ip'              => $this->clean($request->query('ip'), 45),
            'username'        => $this->clean($request->query('username'), 64),
            // These two arrive in the link and can be forged, so only local router addresses are kept.
            'link_login_only' => HotspotUrl::loginUrl($request->query('link-login-only')),
            'link_orig'       => HotspotUrl::destination($request->query('link-orig')),
            'nas'             => $this->clean($request->query('nas'), 60),
            'error'           => $this->clean($request->query('error'), 200),
        ];

        $activeVoucher = null;

        if ($hotspot['mac']) {
            $activeVoucher = Transaction::where('tenant_id', $tenant->id)
                ->where('customer_mac', $hotspot['mac'])
                ->where('status', 'completed')
                ->where('expires_at', '>', now())
                ->orderByDesc('expires_at')
                ->first();
        }

        $locale       = app()->getLocale();
        $contactPhone = $settings?->contact_phone;

        // Which ISP this page is selling for. The page sends it back on every call it makes, so a
        // payment started here can only ever be credited to this ISP, whichever way they arrived.
        $portalKey = TenantUrls::portalKey($tenant);

        // The language links are rebuilt from the cleaned values only, so nothing forged in the
        // incoming address is ever echoed back into the page.
        $portalQuery = array_filter([
            'mac'             => $hotspot['mac'],
            'ip'              => $hotspot['ip'],
            'link-login-only' => $hotspot['link_login_only'],
            'link-orig'       => $hotspot['link_orig'],
            'nas'             => $hotspot['nas'],
        ]);

        return view('portal', compact('tenant', 'packages', 'settings', 'hotspot', 'activeVoucher', 'locale', 'contactPhone', 'portalQuery', 'portalKey'));
    }

    public function initiate(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json(['status' => 'error', 'message' => __('portal.portal_not_ready')], 422);
        }

        $request->validate([
            'phone'           => 'required|string|min:9|max:20',
            'package_id'      => 'required|integer',
            'mac'             => 'nullable|string|max:17',
            'ip'              => 'nullable|string|max:45',
            'link_login_only' => 'nullable|string|max:255',
            'link_orig'       => 'nullable|string|max:255',
            'nas'             => 'nullable|string|max:60',
        ]);

        $package = TenantPackage::where('id', $request->package_id)
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->firstOrFail();

        $phone = Phone::international($request->phone);

        // Someone tapping "pay" twice must not get two prompts on their phone. Within a couple of
        // minutes the same request gets the same transaction back.
        $existing = Transaction::where('tenant_id', $tenant->id)
            ->where('channel', Transaction::CHANNEL_PORTAL)
            ->where('status', 'pending')
            ->where('phone', $request->phone)
            ->where('package_id', $package->id)
            ->whereNotNull('palmpesa_order_id')
            ->where('created_at', '>=', now()->subMinutes(2))
            ->latest('id')
            ->first();

        if ($existing) {
            return response()->json([
                'status'         => 'success',
                'message'        => __('portal.payment_sent'),
                'transaction_id' => $existing->public_id,
            ]);
        }

        // A payment prompt lands on a stranger phone, so cap how many one number can receive.
        $limiterKey = 'pay-phone:' . $phone;

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            return response()->json(['status' => 'error', 'message' => __('portal.err_generic')], 429);
        }

        RateLimiter::hit($limiterKey, 600);

        // Identify router by NAS identifier if provided, otherwise first router
        $router = $tenant->routers()
            ->when($request->nas, fn ($q) => $q->where('nas_identifier', $request->nas))
            ->first() ?? $tenant->routers()->first();

        // Create a pending transaction record before calling PalmPesa
        $transaction = Transaction::create([
            'tenant_id'    => $tenant->id,
            'router_id'    => $router?->id,
            'package_id'   => $package->id,
            'phone'        => $request->phone,
            'amount'       => $package->price,
            'status'       => 'pending',
            'channel'      => Transaction::CHANNEL_PORTAL,
            'locale'       => app()->getLocale(),
            'customer_mac' => $request->mac,
            'customer_ip'  => $request->ip(),
        ]);

        try {
            // All payments go through the platform single PalmPesa account
            $result = PalmPesaService::platform()->initiatePayment([
                'name'   => $tenant->name,
                'phone'  => $request->phone,
                'amount' => $package->price,
            ]);

            // Without an order id there is nothing to check the payment against later.
            if (empty($result['order_id'])) {
                throw new \RuntimeException('The gateway accepted the request but returned no order id.');
            }

            $transaction->update([
                'palmpesa_txn_id'   => $result['palmpesa_txn_id'],
                'palmpesa_order_id' => $result['order_id'],
            ]);

            // Cache hotspot redirect params for use on payment success. The login address is
            // checked again here because this request can be forged as easily as the page link.
            cache()->put(
                'txn_meta_' . $transaction->public_id,
                [
                    'link_login_only' => HotspotUrl::loginUrl($request->link_login_only),
                    'link_orig'       => HotspotUrl::destination($request->link_orig),
                ],
                now()->addMinutes(35)
            );

            return response()->json([
                'status'         => 'success',
                'message'        => __('portal.payment_sent'),
                'transaction_id' => $transaction->public_id,
            ]);
        } catch (\Exception $e) {
            $transaction->update(['status' => 'failed']);
            Log::error('Payment initiation failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);

            // The gateway message can contain internals, so the customer gets a plain one.
            return response()->json(['status' => 'error', 'message' => __('portal.payment_failed')], 500);
        }
    }

    /** Query values are shown back in the page and sent on, so keep them short and printable. */
    private function clean(mixed $value, int $max): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' && preg_match('/^[\x20-\x7E]+$/', $value) ? substr($value, 0, $max) : null;
    }

    /**
     * PalmPesa webhook. Nothing in the payload is trusted, it only tells us which
     * order to look at. The real status always comes from asking PalmPesa directly.
     * Every callback is stored as received before anything acts on it.
     */
    public function callback(Request $request): JsonResponse
    {
        Log::info('PalmPesa callback received', $request->all());

        $orderId = (string) $request->input('order_id', '');
        $inbox   = $this->storeWebhook($request, $orderId);

        $transaction = $orderId !== ''
            ? Transaction::withoutGlobalScopes()->where('palmpesa_order_id', $orderId)->first()
            : null;

        if (! $transaction) {
            Log::warning('Callback: transaction not found', ['order_id' => $orderId]);
            $this->closeWebhook($inbox, 'not_found');

            return response()->json(['status' => 'not_found'], 404);
        }

        if ($transaction->isCompleted()) {
            $this->closeWebhook($inbox, 'already_processed');

            return response()->json(['status' => 'already_processed']);
        }

        $transaction = $this->settlement->verifyAndSettle($transaction);
        $this->closeWebhook($inbox, $transaction->isCompleted() ? 'settled' : $transaction->status);

        return response()->json(['status' => 'received']);
    }

    /** Keeping the raw callback must never be the reason a real payment is missed. */
    private function storeWebhook(Request $request, string $orderId): ?PaymentWebhook
    {
        try {
            return PaymentWebhook::create([
                'provider' => 'palmpesa',
                'order_id' => $orderId !== '' ? $orderId : null,
                'payload'  => $request->all(),
                'ip'       => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Could not store the payment webhook', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function closeWebhook(?PaymentWebhook $inbox, string $result): void
    {
        $inbox?->update(['result' => $result, 'processed_at' => now()]);
    }

    /**
     * Polled by the portal page. The transaction is found by its unguessable public id
     * and the gateway is asked using the order id stored on our own row, never one
     * supplied by the browser.
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $tenant   = tenant();
        $publicId = (string) $request->input('transaction_id', '');

        if ($publicId === '') {
            return response()->json(['status' => 'not_found']);
        }

        $transaction = Transaction::with(['package', 'tenant', 'router'])
            ->where('public_id', $publicId)
            ->where('channel', Transaction::CHANNEL_PORTAL)
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))
            ->first();

        if (! $transaction) {
            return response()->json(['status' => 'not_found']);
        }

        if ($transaction->isPending()) {
            $transaction = $this->settlement->verifyAndSettle($transaction);
        }

        if ($transaction->isCompleted()) {
            $meta = cache()->get('txn_meta_' . $transaction->public_id, []);

            return response()->json([
                'status'     => 'paid',
                'reference'  => $transaction->reference(),
                'access_ready' => app(AgentAccess::class)->isReady(
                    $transaction->router?->isAgent() ? app(AgentAccess::class)->latestGrant((string) $transaction->voucher_code) : null
                ),
                'wifi_token' => $transaction->voucher_code,
                'package'    => $transaction->package?->name,
                'login_url'  => HotspotUrl::loginUrl($meta['link_login_only'] ?? null),
                'dst'        => HotspotUrl::destination($meta['link_orig'] ?? null),
            ]);
        }

        return response()->json(['status' => $transaction->status, 'reference' => $transaction->reference()]);
    }

    /**
     * The portal asks this after a payment or voucher on a router in agent mode: has the router created
     * the customer's user yet? It only says ready or not, nothing else about the login.
     */
    public function accessStatus(Request $request): JsonResponse
    {
        $ref = (string) $request->query('ref', '');

        if (! preg_match('/^[A-Za-z0-9:_.\-]{1,64}$/', $ref)) {
            return response()->json(['ready' => true]);
        }

        return response()->json(['ready' => app(AgentAccess::class)->isReady(app(AgentAccess::class)->latestGrant($ref))]);
    }
}
