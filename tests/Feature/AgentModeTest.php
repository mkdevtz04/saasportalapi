<?php

namespace Tests\Feature;

use App\Jobs\GrantAccessJob;
use App\Models\RouterCommand;
use App\Models\Tenant;
use App\Models\TenantRouter;
use App\Models\TenantWallet;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Notifications\RouterStatusNotification;
use App\Services\AgentAccess;
use App\Services\PaymentSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Agent mode: no RADIUS server and nothing that has to reach the router. The router calls the platform
 * every few seconds and creates the customer's hotspot user itself.
 */
class AgentModeTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://wifikitaa.test']);
    }

    private function agentRouter(Tenant $tenant, array $overrides = []): TenantRouter
    {
        return $this->makeRouter($tenant, array_merge([
            'auth_mode'        => 'agent',
            'router_ip'        => null,
            'username'         => null,
            'password'         => null,
            'nas_identifier'   => 'nas-' . $tenant->id . '-agt',
            'provision_token'  => 'tok-agent',
            'agent_token'      => 'agent-tok',
            'provision_status' => 'completed',
            'last_seen_at'     => now(),
        ], $overrides));
    }

    private function poll(string $token = 'agent-tok'): string
    {
        return $this->get('/api/agent/' . $token . '/poll?v=7.11&n=0')->assertOk()->getContent();
    }

    private function lines(string $body): array
    {
        return array_map('trim', explode("\n", trim($body)));
    }

    // ── Setup ────────────────────────────────────────────────────────────────

    public function test_the_setup_script_needs_no_radius_server_and_no_open_api(): void
    {
        $tenant = $this->makeTenant('testisp');
        $this->agentRouter($tenant);
        config(['radius.host' => '', 'radius.secret' => '']);

        $script = $this->get('/provision/tok-agent')->assertOk()->getContent();

        $this->assertStringContainsString('use-radius=no login-by=cookie,http-pap', $script);
        $this->assertStringContainsString('dst-host="testisp.wifikitaa.test"', $script);
        $this->assertStringContainsString('dst-host="cdnjs.cloudflare.com"', $script);
        $this->assertStringContainsString('https://wifikitaa.test/provision/tok-agent/login.html', $script);
        $this->assertStringContainsString('/api/agent/agent-tok/poll', $script);
        $this->assertStringContainsString('interval=10s', $script, 'a customer waits about one poll after paying');
        $this->assertStringContainsString('/provision/tok-agent/complete\\?failed=', $script);

        $this->assertStringNotContainsString('/radius add', $script);
        $this->assertStringNotContainsString('/ip service enable api', $script);
        $this->assertStringNotContainsString('/user add', $script);
        $this->assertStringNotContainsString('/system identity set', $script, 'the router is not renamed in this mode');
    }

    public function test_every_setup_step_reports_its_own_failure(): void
    {
        $this->agentRouter($this->makeTenant('testisp'));

        $script = $this->get('/provision/tok-agent')->getContent();

        foreach (['hotspot', 'walled-garden', 'login-page', 'agent'] as $step) {
            $this->assertStringContainsString('$failed . "' . $step . ',"', $script);
        }
    }

    public function test_the_agent_script_can_create_and_remove_users_and_never_runs_downloaded_text(): void
    {
        $source = app(\App\Services\ProvisioningScript::class)->agentSource($this->agentRouter($this->makeTenant()));

        $this->assertStringContainsString('"adduser "', $source);
        $this->assertStringContainsString('"removeuser "', $source);
        $this->assertStringContainsString('/ip hotspot user add', $source);
        $this->assertStringContainsString('limit-uptime=[:totime', $source);
        $this->assertStringContainsString('limit-bytes-total=', $source);
        // A lock that could get stuck would stop the router reporting for good, so there is none.
        $this->assertStringNotContainsString(':global', $source, 'nothing may persist between runs and jam the agent');
        $this->assertStringNotContainsString('/import', $source);
        $this->assertStringNotContainsString('&m=', $source, 'the router name only matters for FreeRADIUS');
    }

    public function test_the_login_page_is_available_to_an_agent_router(): void
    {
        $this->agentRouter($this->makeTenant('testisp'));

        $this->get('/provision/tok-agent/login.html')->assertOk()->assertSee('action="$(link-login-only)"', false);
    }

    // ── A payment ────────────────────────────────────────────────────────────

    public function test_a_payment_queues_the_customers_access_in_the_same_transaction_and_calls_no_router(): void
    {
        Queue::fake();
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['name' => 'Daily', 'mikrotik_profile' => 'daily', 'price' => 5000, 'speed_up_mbps' => 2, 'speed_down_mbps' => 5]);
        $router  = $this->agentRouter($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);

        app(PaymentSettlement::class)->verifyAndSettle($payment);

        $payment->refresh();
        $command = RouterCommand::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('completed', $payment->status);
        $this->assertSame(5000, TenantWallet::withoutGlobalScopes()->value('balance'));
        $this->assertSame(RouterCommand::ADD_USER, $command->type);
        $this->assertSame($router->id, $command->router_id);
        $this->assertSame($payment->voucher_code, $command->reference);
        $this->assertSame($payment->voucher_code, $command->payload['username']);
        $this->assertSame('daily', $command->payload['profile']);
        $this->assertSame('2M/5M', $command->payload['rate']);
        $this->assertEqualsWithDelta(86400, $command->payload['seconds'], 5);
        $this->assertSame(0, $command->payload['bytes']);
        $this->assertSame('pending', $payment->provision_status, 'connecting until the router has picked it up');

        Queue::assertNotPushed(GrantAccessJob::class);
        $this->assertSame(0, $this->hotspotUsersCreated(), 'the platform never calls the router');
    }

    public function test_a_data_cap_is_sent_to_the_router_in_bytes(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['data_cap_mb' => 500]);
        $router  = $this->agentRouter($tenant);

        app(AgentAccess::class)->grant($router, $package, 'TNCAP1', now()->addDay());

        $this->assertSame(500 * 1024 * 1024, RouterCommand::withoutGlobalScopes()->firstOrFail()->payload['bytes']);
    }

    public function test_the_router_picks_the_command_up_and_the_customer_becomes_ready_a_moment_later(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['mikrotik_profile' => 'daily']);
        $this->agentRouter($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);

        $status = $this->getJson('/api/payment/status?tenant=acme&transaction_id=' . $payment->public_id)
            ->assertJson(['status' => 'paid', 'access_ready' => false]);
        $token = $status->json('wifi_token');

        // The router asks. It is handed exactly one fixed-shape line.
        $body = $this->poll();
        $this->assertMatchesRegularExpression('/^adduser ' . $token . ' daily 2M\/5M \d{1,9} 0$/m', $body);
        $this->assertSame('done', $payment->fresh()->provision_status);

        // A second poll must not create the user twice.
        $this->assertStringNotContainsString('adduser', $this->poll());

        // The router needs a moment to act, so the portal is not told "ready" straight away.
        $this->getJson('/api/access/status?ref=' . $token)->assertJson(['ready' => false]);
        $this->getJson('/api/payment/status?tenant=acme&transaction_id=' . $payment->public_id)->assertJson(['access_ready' => false]);

        $this->travel(4)->seconds();

        $this->getJson('/api/access/status?ref=' . $token)->assertJson(['ready' => true]);
        $this->getJson('/api/payment/status?tenant=acme&transaction_id=' . $payment->public_id)->assertJson(['access_ready' => true]);
    }

    public function test_routers_connected_other_ways_never_make_the_customer_wait(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->makeRouter($tenant);   // API mode
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);

        $this->getJson('/api/payment/status?tenant=acme&transaction_id=' . $payment->public_id)
            ->assertJson(['status' => 'paid', 'access_ready' => true]);
        $this->getJson('/api/access/status?ref=UNKNOWNREF1')->assertJson(['ready' => true]);
        $this->getJson('/api/access/status?ref=' . urlencode('bad ref!'))->assertJson(['ready' => true]);
    }

    // ── A voucher ────────────────────────────────────────────────────────────

    public function test_redeeming_a_voucher_on_an_agent_router_queues_the_user_and_the_portal_waits(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['mikrotik_profile' => 'daily']);
        $this->agentRouter($tenant);
        $voucher = Voucher::create(['tenant_id' => $tenant->id, 'package_id' => $package->id, 'code' => 'TNVOUCH123']);
        $this->fakeExternalServices([], routerOk: false);   // a dead router changes nothing

        $this->postJson('/api/voucher/redeem?tenant=acme', ['code' => 'tnvouch123'])
            ->assertOk()
            ->assertJson(['ok' => true, 'access_ready' => false, 'access_ref' => 'TNVOUCH123']);

        $this->assertNotNull($voucher->fresh()->used_at);
        $this->assertSame(0, TenantWallet::withoutGlobalScopes()->count(), 'a voucher never fills the wallet');
        $this->assertSame(0, $this->hotspotUsersCreated());

        $this->getJson('/api/access/status?ref=TNVOUCH123')->assertJson(['ready' => false]);

        $this->assertStringContainsString('adduser TNVOUCH123 daily 2M/5M', $this->poll());
        $this->travel(4)->seconds();

        $this->getJson('/api/access/status?ref=TNVOUCH123')->assertJson(['ready' => true]);
    }

    // ── The reply is inert data ──────────────────────────────────────────────

    public function test_values_that_are_not_plain_are_never_sent_to_the_router(): void
    {
        $router = $this->agentRouter($this->makeTenant());

        $router->queueCommand(RouterCommand::ADD_USER, ['username' => 'TNOK1', 'profile' => 'good', 'rate' => '2M/5M', 'seconds' => 3600, 'bytes' => 0], null, 'TNOK1');
        $router->queueCommand(RouterCommand::ADD_USER, ['username' => "TN;/system reset-configuration", 'profile' => 'good', 'rate' => '2M/5M', 'seconds' => 3600, 'bytes' => 0]);
        $router->queueCommand(RouterCommand::ADD_USER, ['username' => 'TNBAD2', 'profile' => 'x;/user add', 'rate' => '2M/5M', 'seconds' => 3600, 'bytes' => 0]);
        $router->queueCommand(RouterCommand::ADD_USER, ['username' => 'TNBAD3', 'profile' => 'good', 'rate' => '2M/5M; drop', 'seconds' => 3600, 'bytes' => 0]);
        $router->queueCommand(RouterCommand::ADD_USER, ['username' => 'TNBAD4', 'profile' => 'good', 'rate' => '2M/5M', 'seconds' => '3600 5', 'bytes' => 0]);
        $router->queueCommand(RouterCommand::ADD_USER, ['username' => 'TNBAD5', 'profile' => 'good', 'rate' => '2M/5M', 'seconds' => 3600, 'bytes' => '-1']);
        $router->queueCommand(RouterCommand::REMOVE_USER, ['username' => "x\n/system reset-configuration"]);
        $router->queueCommand(RouterCommand::REMOVE_USER, ['username' => 'TNGONE1']);

        $body = $this->poll();

        foreach ($this->lines($body) as $line) {
            $this->assertMatchesRegularExpression(
                '/^(#.*|reboot|kick_all|kick [A-Za-z0-9:_.\-]{1,64}|removeuser [A-Za-z0-9:_.\-]{1,64}|adduser [A-Za-z0-9:_.\-]{1,64} [A-Za-z0-9_\-]{1,40} \d{1,5}M\/\d{1,5}M \d{1,9} \d{1,12})$/',
                $line,
                'unexpected line: ' . $line
            );
        }

        $this->assertStringContainsString('adduser TNOK1 good 2M/5M 3600 0', $body);
        $this->assertStringContainsString('removeuser TNGONE1', $body);
        $this->assertStringNotContainsString('reset-configuration', $body);
        $this->assertStringNotContainsString('TNBAD', $body);
    }

    public function test_a_package_name_with_odd_characters_becomes_a_safe_profile_name(): void
    {
        $tenant  = $this->makeTenant();
        $router  = $this->agentRouter($tenant);
        $package = $this->makePackage($tenant, ['name' => 'Fast; /system reset', 'mikrotik_profile' => 'fast;/x y']);

        app(AgentAccess::class)->grant($router, $package, 'TNSAFE1', now()->addDay());

        $this->assertSame('fastxy', RouterCommand::withoutGlobalScopes()->firstOrFail()->payload['profile']);
        $this->assertSame('tn-package', AgentAccess::profileName($this->makePackage($tenant, ['name' => ';;;', 'mikrotik_profile' => ';;;'])));
    }

    // ── Expiry ───────────────────────────────────────────────────────────────

    public function test_customers_whose_time_ran_out_are_removed_once(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $router  = $this->agentRouter($tenant);
        $gone    = $this->makePendingPayment($tenant, $package, 'ORD-1', ['status' => 'completed', 'voucher_code' => 'TNGONE1', 'expires_at' => now()->subMinute()]);
        $active  = $this->makePendingPayment($tenant, $package, 'ORD-2', ['status' => 'completed', 'voucher_code' => 'TNLIVE1', 'expires_at' => now()->addHour()]);

        $this->artisan('access:expire')->assertSuccessful();
        $this->artisan('access:expire')->assertSuccessful();

        $commands = RouterCommand::withoutGlobalScopes()->where('router_id', $router->id)->get();
        $this->assertCount(1, $commands, 'removed once, never twice');
        $this->assertSame(RouterCommand::REMOVE_USER, $commands[0]->type);
        $this->assertSame('TNGONE1', $commands[0]->reference);
        $this->assertNotNull($gone->fresh()->access_ended_at);
        $this->assertNull($active->fresh()->access_ended_at);

        $this->assertStringContainsString('removeuser TNGONE1', $this->poll());
        $this->assertStringNotContainsString('TNLIVE1', $this->poll());
    }

    public function test_expiry_leaves_routers_connected_other_ways_alone(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $api     = $this->makeRouter($tenant);
        $old     = $this->makePendingPayment($tenant, $package, 'ORD-1', ['status' => 'completed', 'voucher_code' => 'TNAPI1', 'expires_at' => now()->subMinute()]);

        $this->artisan('access:expire')->assertSuccessful();

        $this->assertSame(0, RouterCommand::withoutGlobalScopes()->count());
        $this->assertNotNull($old->fresh()->access_ended_at, 'marked so the query does not keep finding it');
    }

    // ── Suspension ───────────────────────────────────────────────────────────

    public function test_suspending_an_isp_removes_its_customers_and_lifting_it_gives_back_their_time(): void
    {
        $tenant  = $this->makeTenant();
        $other   = $this->makeTenant('other');
        $package = $this->makePackage($tenant, ['mikrotik_profile' => 'daily']);
        $router  = $this->agentRouter($tenant);
        $mine    = $this->makePendingPayment($tenant, $package, 'ORD-1', ['status' => 'completed', 'voucher_code' => 'TNMINE1', 'expires_at' => now()->addHours(5), 'router_id' => $router->id]);
        $otherPackage = $this->makePackage($other);
        $otherRouter  = $this->agentRouter($other, ['nas_identifier' => 'nas-2-agt', 'provision_token' => 'tok-other', 'agent_token' => 'agent-other']);
        $this->makePendingPayment($other, $otherPackage, 'ORD-2', ['status' => 'completed', 'voucher_code' => 'TNOTHER1', 'expires_at' => now()->addHours(5), 'router_id' => $otherRouter->id]);
        $this->actingAs($this->makeAdmin(), 'admin');

        $this->post("/admin/tenants/{$tenant->id}/suspend")->assertRedirect();

        $body = $this->poll();
        $this->assertStringContainsString('removeuser TNMINE1', $body);
        $this->assertStringContainsString('kick_all', $body);
        $this->assertNotNull($mine->fresh()->access_ended_at);
        $this->assertSame(0, RouterCommand::withoutGlobalScopes()->where('router_id', $otherRouter->id)->count(), 'another ISP is untouched');

        $this->post("/admin/tenants/{$tenant->id}/activate")->assertRedirect();

        $back = $this->poll();
        $this->assertMatchesRegularExpression('/^adduser TNMINE1 daily 2M\/5M \d{4,5} 0$/m', $back, 'the time left is given back');
        $this->assertNull($mine->fresh()->access_ended_at);
    }

    // ── Choosing the mode ────────────────────────────────────────────────────

    public function test_a_new_router_uses_agent_mode_by_default_and_needs_only_a_name(): void
    {
        $tenant = $this->makeTenant('testisp');
        $this->actingAs($this->makeOwner($tenant), 'tenant');

        $response = $this->post('/dashboard/routers', ['name' => 'Office router', 'mode' => 'agent']);

        $router = TenantRouter::withoutGlobalScopes()->firstOrFail();
        $response->assertRedirect(route('dashboard.routers.edit', $router));
        $this->assertSame('agent', $router->auth_mode);
        $this->assertNull($router->router_ip);

        $this->get(route('dashboard.routers.edit', $router))
            ->assertOk()
            ->assertSee('trinetpay-bootstrap.rsc')
            ->assertSee('/provision/' . $router->provision_token, false);
    }

    public function test_when_no_mode_is_chosen_the_configured_default_applies(): void
    {
        $tenant = $this->makeTenant();
        $this->actingAs($this->makeOwner($tenant), 'tenant');

        $this->post('/dashboard/routers', ['name' => 'One'])->assertRedirect();
        $this->assertSame('agent', TenantRouter::withoutGlobalScopes()->firstOrFail()->auth_mode);

        config(['router.default_mode' => 'nonsense']);
        $this->post('/dashboard/routers', ['name' => 'Two'])->assertRedirect();
        $this->assertSame('agent', TenantRouter::withoutGlobalScopes()->orderByDesc('id')->firstOrFail()->auth_mode, 'an unknown default falls back to agent');
    }

    public function test_freeradius_can_only_be_chosen_once_it_is_set_up(): void
    {
        $tenant = $this->makeTenant();
        $this->actingAs($this->makeOwner($tenant), 'tenant');

        config(['radius.host' => '', 'radius.secret' => '']);
        $this->post('/dashboard/routers', ['name' => 'Radius', 'mode' => 'radius'])->assertSessionHasErrors('name');
        $this->assertSame(0, TenantRouter::withoutGlobalScopes()->count());

        config(['radius.host' => '203.0.113.10', 'radius.secret' => 'secret']);
        $this->post('/dashboard/routers', ['name' => 'Radius', 'mode' => 'radius'])->assertRedirect();
        $this->assertSame('radius', TenantRouter::withoutGlobalScopes()->firstOrFail()->auth_mode);
    }

    public function test_the_mode_a_router_is_switched_to_never_needs_a_server_that_is_missing(): void
    {
        config(['router.default_mode' => 'radius', 'radius.host' => '', 'radius.secret' => '']);
        $this->assertSame('agent', TenantRouter::connectMode());

        config(['radius.host' => '203.0.113.10', 'radius.secret' => 'secret']);
        $this->assertSame('radius', TenantRouter::connectMode());

        config(['router.default_mode' => 'api']);
        $this->assertSame('agent', TenantRouter::connectMode(), 'the old mode is never a target');
    }

    public function test_the_onboarding_wizard_creates_an_agent_router(): void
    {
        $tenant = $this->makeTenant('testisp', ['status' => 'onboarding']);

        $this->actingAs($this->makeOwner($tenant), 'tenant')->get('/onboarding/router')->assertOk();

        $this->assertSame('agent', TenantRouter::withoutGlobalScopes()->firstOrFail()->auth_mode);
    }

    public function test_an_old_api_router_can_be_switched_to_agent_mode(): void
    {
        $tenant = $this->makeTenant();
        $old    = $this->makeRouter($tenant, ['provision_token' => 'old_tok']);
        $this->actingAs($this->makeOwner($tenant), 'tenant');

        $this->post("/dashboard/routers/{$old->id}/switch")->assertRedirect();

        $old->refresh();
        $this->assertSame('agent', $old->auth_mode);
        $this->assertNotSame('old_tok', $old->provision_token);
    }

    // ── Health, alerts and commands work the same ────────────────────────────

    public function test_an_agent_router_that_goes_quiet_is_reported_like_any_other(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();
        $owner  = $this->makeOwner($tenant);
        $router = $this->agentRouter($tenant, ['status' => 'online', 'last_seen_at' => now()->subMinutes(30)]);

        $this->artisan('router:heartbeat')->assertSuccessful();

        Notification::assertSentTo($owner, RouterStatusNotification::class);
        $this->assertSame('offline', $router->fresh()->status);
    }

    public function test_the_owner_can_reboot_or_disconnect_everyone_on_an_agent_router(): void
    {
        $tenant = $this->makeTenant();
        $router = $this->agentRouter($tenant);
        $this->actingAs($this->makeOwner($tenant), 'tenant');

        $this->post("/dashboard/routers/{$router->id}/command", ['type' => 'reboot'])->assertSessionHas('success');

        $this->assertContains('reboot', $this->lines($this->poll()));
    }

    public function test_an_agent_router_polling_does_not_claim_a_wrong_router_name(): void
    {
        $router = $this->agentRouter($this->makeTenant());

        $this->get('/api/agent/agent-tok/poll?v=7.11&n=3')->assertOk();

        $this->assertNull($router->fresh()->identity_ok);
        $this->assertSame(3, $router->fresh()->active_users);
    }

    public function test_the_scheduler_removes_expired_access_every_minute(): void
    {
        \Illuminate\Support\Facades\Artisan::call('schedule:list');

        $this->assertStringContainsString('access:expire', \Illuminate\Support\Facades\Artisan::output());
    }
}
