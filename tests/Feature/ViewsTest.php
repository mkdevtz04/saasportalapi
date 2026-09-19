<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Voucher;
use App\Models\WithdrawalRequest;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Two safety nets for pages: every template must be valid PHP once compiled, and every
 * screen must actually render with real data. A stray @endif only fails when the page is
 * opened, so a page nobody opens in a test can break without anyone noticing.
 */
class ViewsTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_every_template_compiles_to_valid_php(): void
    {
        $root  = resource_path('views');
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        $count = 0;

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $count++;
            $php = Blade::compileString(file_get_contents($file->getPathname()));

            try {
                token_get_all($php, TOKEN_PARSE);
            } catch (\ParseError $e) {
                $this->fail(str_replace($root, 'views', $file->getPathname()) . ' does not compile to valid PHP: ' . $e->getMessage() . ' (compiled line ' . $e->getLine() . ')');
            }
        }

        $this->assertGreaterThan(30, $count, 'the templates were not found');
    }

    public function test_every_screen_renders_with_real_data(): void
    {
        $tenant  = $this->makeTenant('acme', ['name' => 'Acme WiFi']);
        $tenant->settings()->create(['brand_color' => '#0b7a75', 'default_language' => 'sw', 'withdrawal_number' => '0712345678']);
        $owner   = $this->makeOwner($tenant);
        $package = $this->makePackage($tenant, ['name' => 'Daily']);
        $router  = $this->makeRouter($tenant, ['provision_token' => 'tok-legacy']);
        $radius  = $this->makeRouter($tenant, [
            'auth_mode' => 'radius', 'router_ip' => null, 'username' => null, 'password' => null,
            'nas_identifier' => 'nas-' . $tenant->id . '-abc', 'provision_token' => 'tok-radius', 'agent_token' => 'agent-tok',
            'provision_status' => 'completed', 'last_seen_at' => now(), 'identity_ok' => false,
        ]);
        $agent   = $this->makeAgent($tenant, 5000);
        $wallet  = $this->makeWallet($tenant, 20000);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1', ['status' => 'completed', 'voucher_code' => 'TNRENDER1', 'provision_status' => 'failed']);
        Voucher::create(['tenant_id' => $tenant->id, 'package_id' => $package->id, 'code' => 'TNBATCHED1', 'batch_ref' => 'BTCHVIEW']);
        $withdrawal = WithdrawalRequest::create([
            'tenant_id' => $tenant->id, 'amount' => 6000, 'fee_amount' => 300, 'net_amount' => 5700,
            'mobile_number' => '0799000111', 'status' => 'pending',
        ]);
        Audit::record('test.action', $tenant->id, ['key' => 'value']);
        DB::table('radacct')->insert([
            'acctsessionid' => 's1', 'acctuniqueid' => 'u1', 'username' => 'TNRENDER1', 'nasipaddress' => '1.1.1.1',
            'acctstarttime' => now()->utc()->subMinutes(10), 'acctupdatetime' => now()->utc(),
            'acctinputoctets' => 1000, 'acctoutputoctets' => 5000, 'calledstationid' => '', 'callingstationid' => 'AA:BB:CC:DD:EE:FF',
            'acctterminatecause' => '', 'framedipaddress' => '10.0.0.5', 'framedipv6address' => '', 'framedipv6prefix' => '',
            'framedinterfaceid' => '', 'delegatedipv6prefix' => '',
        ]);
        app(\App\Services\Radius\RadiusAccess::class)->grant($tenant->id, $package, 'TNRENDER1', 'TNRENDER1', now()->addDay(), 'txn:1');

        // Public pages
        foreach (['/', '/login', '/register', '/portal?tenant=acme', '/portal?tenant=acme&lang=en', '/admin/login', '/provision/tok-radius/login.html'] as $url) {
            $this->get($url)->assertOk();
        }

        // ISP owner screens
        $this->actingAs($owner, 'tenant');
        foreach ([
            '/dashboard', '/dashboard/transactions', '/dashboard/transactions?access=failed&ref=ABC&channel=voucher',
            '/dashboard/sessions', '/dashboard/reports', '/dashboard/reports?from=2026-01-01&to=2026-12-31',
            '/dashboard/routers', '/dashboard/routers/create', '/dashboard/routers/create?mode=api',
            "/dashboard/routers/{$router->id}/edit", "/dashboard/routers/{$radius->id}/edit",
            '/dashboard/packages', '/dashboard/packages/create', "/dashboard/packages/{$package->id}/edit",
            '/dashboard/vouchers', '/dashboard/vouchers/generate', '/dashboard/vouchers/BTCHVIEW/print',
            '/dashboard/agents', '/dashboard/agents/create',
            '/dashboard/wallet', '/dashboard/settings',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        // Onboarding wizard
        foreach (['/onboarding/router', '/onboarding/packages', '/onboarding/payment'] as $url) {
            $this->get($url)->assertOk();
        }

        // Agent point of sale
        $this->actingAs($agent, 'tenant')->get('/pos')->assertOk();

        // Platform admin screens
        auth('tenant')->logout();
        $this->actingAs($this->makeAdmin(), 'admin');
        foreach ([
            '/admin', '/admin/tenants', "/admin/tenants/{$tenant->id}", '/admin/tenants?status=onboarding&q=acme',
            '/admin/withdrawals', '/admin/withdrawals?status=all',
            '/admin/reconciliation', '/admin/audit', '/admin/audit?isp=' . $tenant->id . '&action=test',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->assertGreaterThan(0, AuditLog::count());
        $this->assertNotNull($payment->id.$wallet->id.$withdrawal->id);
    }

    public function test_a_suspended_isp_portal_shows_the_notice_page(): void
    {
        $this->makeTenant('acme', ['status' => 'suspended']);

        $this->get('/portal?tenant=acme')->assertStatus(503);
    }
}
