<?php

namespace Tests\Concerns;

use App\Models\AgentWallet;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\TenantPackage;
use App\Models\TenantRouter;
use App\Models\TenantUser;
use App\Models\TenantWallet;
use App\Models\Transaction;
use App\Models\WalletEntry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

trait BuildsTenants
{
    protected function makeTenant(string $subdomain = 'acme', array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'name'      => ucfirst($subdomain) . ' WiFi',
            'subdomain' => $subdomain,
            'status'    => 'active',
        ], $overrides));
    }

    protected function makePackage(Tenant $tenant, array $overrides = []): TenantPackage
    {
        return TenantPackage::create(array_merge([
            'tenant_id'        => $tenant->id,
            'name'             => 'Daily',
            'price'            => 5000,
            'duration_hours'   => 24,
            'speed_down_mbps'  => 5,
            'speed_up_mbps'    => 2,
            'mikrotik_profile' => 'daily',
        ], $overrides));
    }

    protected function makeRouter(Tenant $tenant, array $overrides = []): TenantRouter
    {
        return TenantRouter::create(array_merge([
            'tenant_id'      => $tenant->id,
            'name'           => 'Main router',
            'router_ip'      => '192.168.88.1',
            'username'       => 'trinetpay',
            'password'       => 'secret-pass',
            'port'           => 8728,
            'nas_identifier' => 'nas-' . $tenant->id . '-' . Str::random(8),
        ], $overrides));
    }

    protected function makeOwner(Tenant $tenant, string $email = 'owner@example.test'): TenantUser
    {
        return TenantUser::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Owner',
            'email'     => $email,
            'password'  => 'password',
            'role'      => 'owner',
        ]);
    }

    protected function makeAgent(Tenant $tenant, int $walletBalance = 0, string $email = 'agent@example.test'): TenantUser
    {
        $agent = TenantUser::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Agent',
            'email'     => $email,
            'password'  => 'password',
            'role'      => 'agent',
        ]);

        AgentWallet::create(['tenant_user_id' => $agent->id, 'balance' => $walletBalance]);

        return $agent;
    }

    protected function makeAdmin(): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name'     => 'Admin',
            'email'    => 'admin@example.test',
            'password' => 'password',
        ]);
    }

    protected function makeWallet(Tenant $tenant, int $balance = 0, ?int $totalEarned = null): TenantWallet
    {
        $wallet = TenantWallet::create([
            'tenant_id'    => $tenant->id,
            'balance'      => $balance,
            'total_earned' => $totalEarned ?? $balance,
        ]);

        if ($balance > 0) {
            WalletEntry::create([
                'tenant_id'     => $tenant->id,
                'type'          => WalletEntry::OPENING_BALANCE,
                'amount'        => $balance,
                'balance_after' => $balance,
                'reference'     => 'opening',
            ]);
        }

        return $wallet;
    }

    protected function makePendingPayment(Tenant $tenant, TenantPackage $package, ?string $orderId, array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'tenant_id'         => $tenant->id,
            'router_id'         => $tenant->routers()->value('id'),
            'package_id'        => $package->id,
            'phone'             => '0712345678',
            'amount'            => $package->price,
            'status'            => 'pending',
            'channel'           => Transaction::CHANNEL_PORTAL,
            'palmpesa_order_id' => $orderId,
        ], $overrides));
    }

    /** @var array<string,string> gateway status per order id */
    protected array $gatewayOrders = [];

    protected bool $routerAccepts = true;

    private bool $externalServicesFaked = false;

    /**
     * Fake PalmPesa and the router relay. Safe to call again inside one test to change
     * what the gateway or the router answers from that point on.
     *
     * @param array<string,string> $orders  gateway status per order id, e.g. ['ORD-1' => 'COMPLETED'].
     *                                      Any order not listed is reported as PENDING.
     * @param bool $routerOk                whether the router accepts the new hotspot user
     */
    protected function fakeExternalServices(array $orders = [], bool $routerOk = true): void
    {
        $this->gatewayOrders = $orders;
        $this->routerAccepts = $routerOk;

        if ($this->externalServicesFaked) {
            return;
        }

        $this->externalServicesFaked = true;

        config([
            'services.palmpesa.base_url'      => 'https://palmpesa.test',
            'services.mikrotik.relay_enabled' => true,
            'services.mikrotik.relay_url'     => 'https://relay.test/agent',
            'services.mikrotik.relay_secret'  => 'relay-secret',
        ]);

        Http::fake([
            'palmpesa.test/api/order-status' => function (Request $request) {
                $status = $this->gatewayOrders[$request['order_id']] ?? 'PENDING';

                return Http::response(['data' => [['payment_status' => $status]]]);
            },
            'palmpesa.test/api/pay-via-mobile' => Http::response(['order_id' => 'ORD-NEW']),
            'relay.test/*' => function () {
                return Http::response($this->routerAccepts ? ['ok' => true] : ['ok' => false, 'error' => 'router down']);
            },
        ]);
    }

    /** How many hotspot users were requested from the router so far. */
    protected function hotspotUsersCreated(): int
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), 'relay.test')
            && ($request['action'] ?? null) === 'create_hotspot_user')->count();
    }
}
