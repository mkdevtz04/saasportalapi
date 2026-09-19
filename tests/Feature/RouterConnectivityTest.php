<?php

namespace Tests\Feature;

use App\Models\RouterCommand;
use App\Models\Tenant;
use App\Models\TenantRouter;
use App\Models\TenantWallet;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\PaymentSettlement;
use App\Services\Radius\RadiusAccess;
use App\Support\RouterOs;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Routers connect out to the platform: RADIUS for customer logins, and a small agent
 * script on the router for heartbeat and commands.
 */
class RouterConnectivityTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://trinetpay.test']);
    }

    private function radiusRouter(Tenant $tenant, array $overrides = []): TenantRouter
    {
        return $this->makeRouter($tenant, array_merge([
            'auth_mode'       => 'radius',
            'router_ip'       => null,
            'username'        => null,
            'password'        => null,
            'nas_identifier'  => 'nas-' . $tenant->id . '-abc12345',
            'provision_token' => 'trinet_prov_tok',
            'agent_token'     => 'trinet_agent_tok',
        ], $overrides));
    }

    private function radcheck(string $username): array
    {
        return DB::table('radcheck')->where('username', $username)->get()
            ->mapWithKeys(fn ($row) => [$row->attribute => [$row->op, $row->value]])->all();
    }

    private function radreply(string $username): array
    {
        return DB::table('radreply')->where('username', $username)->pluck('value', 'attribute')->all();
    }

    // ── RouterOS escaping ────────────────────────────────────────────────────

    public function test_quoting_escapes_everything_that_could_end_a_string_or_start_a_command(): void
    {
        $this->assertSame('"plain"', RouterOs::quote('plain'));
        $this->assertSame('"a\\"b"', RouterOs::quote('a"b'));
        $this->assertSame('"a\\\\b"', RouterOs::quote('a\\b'));
        $this->assertSame('"\\$(x)"', RouterOs::quote('$(x)'));
        $this->assertSame('"what\\?"', RouterOs::quote('what?'));
        $this->assertSame('"one\\ntwo"', RouterOs::quote("one\ntwo"));
        $this->assertSame('"ab"', RouterOs::quote("a\x00b"), 'control characters are dropped');
    }

    public function test_comments_and_bare_names_stay_on_one_safe_line(): void
    {
        $this->assertSame('one two', RouterOs::comment("one\ntwo"));
        $this->assertSame('mynas-1x', RouterOs::bareName('my nas-1; /x'), 'spaces, semicolons and slashes are removed');
        $this->assertSame('fallback', RouterOs::bareName('!!!', 'fallback'));
    }

    public function test_the_router_api_username_comes_from_the_tenant_name_and_never_collides_with_admin(): void
    {
        $this->assertSame('tn_juma_wifi', TenantRouter::apiUsernameFor(new Tenant(['name' => 'Juma WiFi'])));
        $this->assertSame('tn_admin', TenantRouter::apiUsernameFor(new Tenant(['name' => 'Admin'])), 'must not be the built-in admin account');
        $this->assertSame('tn_isp', TenantRouter::apiUsernameFor(new Tenant(['name' => '!!!'])));
        $this->assertLessThanOrEqual(27, strlen(TenantRouter::apiUsernameFor(new Tenant(['name' => str_repeat('a', 80)]))));
    }

    // ── Setup script ─────────────────────────────────────────────────────────

    public function test_the_setup_script_connects_the_router_out_and_exposes_nothing(): void
    {
        $tenant = $this->makeTenant('testisp');
        $router = $this->radiusRouter($tenant);

        $script = $this->get('/provision/trinet_prov_tok')->assertOk()->getContent();

        $this->assertStringContainsString('/system identity set name="' . $router->nas_identifier . '"', $script);
        $this->assertStringContainsString('/radius add address="203.0.113.10" secret="test-radius-secret"', $script);
        $this->assertStringContainsString('use-radius=yes', $script);
        $this->assertStringContainsString('dst-host="testisp.trinetpay.test"', $script);
        $this->assertStringContainsString('dst-host="cdnjs.cloudflare.com"', $script);
        $this->assertStringContainsString('https://trinetpay.test/provision/trinet_prov_tok/login.html', $script);
        $this->assertStringContainsString('/api/agent/trinet_agent_tok/poll', $script);
        $this->assertStringContainsString('/provision/trinet_prov_tok/complete\?failed=', $script);

        $this->assertStringNotContainsString('/ip service enable api', $script, 'the router must not open its API');
        $this->assertStringNotContainsString('/user add', $script, 'no account is created on the router');
    }

    public function test_every_setup_step_reports_its_own_failure_so_one_bad_command_cannot_stop_the_rest(): void
    {
        $this->radiusRouter($this->makeTenant('testisp'));

        $script = $this->get('/provision/trinet_prov_tok')->getContent();

        foreach (['identity', 'radius', 'hotspot', 'walled-garden', 'login-page', 'agent'] as $step) {
            $this->assertStringContainsString('$failed . "' . $step . ',"', $script);
        }
    }

    public function test_the_setup_script_refuses_to_run_until_radius_is_configured(): void
    {
        $this->radiusRouter($this->makeTenant('testisp'));
        config(['radius.host' => '', 'radius.secret' => '']);

        $this->get('/provision/trinet_prov_tok')
            ->assertStatus(503)
            ->assertSee('not ready', false);
    }

    public function test_a_router_name_cannot_inject_commands_into_the_radius_script(): void
    {
        $this->radiusRouter($this->makeTenant('testisp'), ['name' => "Shop\n/system reset-configuration\n"]);

        $script = $this->get('/provision/trinet_prov_tok')->getContent();

        foreach (explode("\n", $script) as $line) {
            $this->assertStringStartsNotWith('/system reset-configuration', trim($line));
        }
    }

    public function test_the_one_line_command_uses_the_configured_address(): void
    {
        $router = $this->radiusRouter($this->makeTenant('testisp'));

        $command = app(\App\Services\ProvisioningScript::class)->oneLiner($router);

        $this->assertStringContainsString('url="https://trinetpay.test/provision/trinet_prov_tok"', $command);
        $this->assertStringContainsString('/import file-name=trinetpay-bootstrap.rsc', $command);
        $this->assertStringNotContainsString(':import', $command);
    }

    public function test_the_router_reports_which_steps_failed_and_the_tenant_sees_it(): void
    {
        $router = $this->radiusRouter($this->makeTenant('testisp'));

        $this->get('/provision/trinet_prov_tok/complete?failed=hotspot,login-page,')->assertOk();
        $router->refresh();
        $this->assertSame('failed', $router->provision_status);
        $this->assertSame('These steps did not work: hotspot, login-page', $router->provision_note);

        $this->get('/provision/trinet_prov_tok/complete?failed=')->assertOk();
        $router->refresh();
        $this->assertSame('completed', $router->provision_status);
        $this->assertNull($router->provision_note);

        $this->getJson('/provision/trinet_prov_tok/status')->assertJson(['status' => 'completed', 'note' => null]);
    }

    public function test_reported_step_names_are_cleaned_before_they_are_stored(): void
    {
        $router = $this->radiusRouter($this->makeTenant('testisp'));

        $this->get('/provision/trinet_prov_tok/complete?failed=' . urlencode('<script>alert(1)</script>,radius'))->assertOk();

        $this->assertStringNotContainsString('<', $router->fresh()->provision_note);
    }

    // ── Login page ───────────────────────────────────────────────────────────

    public function test_the_login_page_is_branded_and_links_customers_to_their_own_portal(): void
    {
        $tenant = $this->makeTenant('testisp', ['name' => 'Juma <b>WiFi</b>']);
        $router = $this->radiusRouter($tenant);

        $html = $this->get('/provision/trinet_prov_tok/login.html')->assertOk()->getContent();

        $this->assertStringContainsString('Juma &lt;b&gt;WiFi&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>WiFi</b>', $html);
        $this->assertStringContainsString('https://testisp.trinetpay.test/portal?mac=$(mac-esc)', $html);
        $this->assertStringContainsString('nas=' . $router->nas_identifier, $html);
        $this->assertStringContainsString('action="$(link-login-only)"', $html);
    }

    public function test_only_one_command_routers_get_a_login_page(): void
    {
        $tenant = $this->makeTenant('testisp');
        $this->makeRouter($tenant, ['provision_token' => 'trinet_prov_api']);

        $this->get('/provision/trinet_prov_api/login.html')->assertNotFound();
        $this->get('/provision/nope/login.html')->assertNotFound();
    }

    // ── Agent heartbeat and commands ─────────────────────────────────────────

    public function test_the_agent_poll_reports_the_router_online_and_records_what_it_says_about_itself(): void
    {
        $tenant = $this->makeTenant();
        $router = $this->radiusRouter($tenant);

        $this->get('/api/agent/trinet_agent_tok/poll?v=7.11.2&n=14&u=1w2d03:04:05')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $router->refresh();
        $this->assertSame('online', $router->status);
        $this->assertTrue($router->isOnline());
        $this->assertSame('7.11.2', $router->routeros_version);
        $this->assertSame(14, $router->active_users);
        $this->assertSame('1w2d03:04:05', $router->router_uptime);
        $this->assertNotNull($router->public_ip);
        $this->assertSame('completed', $router->provision_status, 'a polling router has clearly been set up');
    }

    public function test_the_agent_reports_whether_the_router_still_has_its_platform_name(): void
    {
        $tenant = $this->makeTenant();
        $router = $this->radiusRouter($tenant);

        $this->get('/api/agent/trinet_agent_tok/poll?v=7.11&n=1&m=0')->assertOk();
        $this->assertFalse($router->fresh()->identity_ok);

        $this->get('/api/agent/trinet_agent_tok/poll?v=7.11&n=1&m=1')->assertOk();
        $this->assertTrue($router->fresh()->identity_ok);

        // An older agent that does not send it leaves the last answer alone.
        $this->get('/api/agent/trinet_agent_tok/poll?v=7.11&n=1')->assertOk();
        $this->assertTrue($router->fresh()->identity_ok);
    }

    public function test_the_agent_script_compares_the_router_name_with_the_one_the_platform_gave(): void
    {
        $router = $this->radiusRouter($this->makeTenant('testisp'));

        $script = app(\App\Services\ProvisioningScript::class)->agentSource($router);

        $this->assertStringContainsString('[/system identity get name] = "' . $router->nas_identifier . '"', $script);
        $this->assertStringContainsString('&m=', $script);
    }

    public function test_the_owner_is_warned_when_a_router_was_renamed(): void
    {
        $tenant = $this->makeTenant();
        $router = $this->radiusRouter($tenant, ['name' => 'Kariakoo', 'identity_ok' => false, 'last_seen_at' => now()]);
        $owner  = $this->makeOwner($tenant);

        $this->actingAs($owner, 'tenant')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Customers cannot log in on Kariakoo');

        $this->get('/dashboard/routers/' . $router->id . '/edit')
            ->assertOk()
            ->assertSee('The router name was changed')
            ->assertSee('/system identity set name=' . $router->nas_identifier, false);

        $router->update(['identity_ok' => true]);
        $this->get('/dashboard')->assertDontSee('Customers cannot log in on');
    }

    public function test_an_unknown_agent_token_gets_nothing(): void
    {
        $this->get('/api/agent/not-a-router/poll')->assertNotFound();
    }

    public function test_reported_values_are_reduced_to_plain_text(): void
    {
        $router = $this->radiusRouter($this->makeTenant());

        $this->get('/api/agent/trinet_agent_tok/poll?v=' . urlencode('<img src=x onerror=alert(1)>') . '&n=abc&u=' . urlencode('"; drop'))->assertOk();

        $router->refresh();
        $this->assertStringNotContainsString('<', (string) $router->routeros_version);
        $this->assertSame(0, $router->active_users);
        $this->assertStringNotContainsString('"', (string) $router->router_uptime);
    }

    public function test_commands_are_handed_to_the_router_exactly_once(): void
    {
        $router = $this->radiusRouter($this->makeTenant());
        $router->queueCommand(RouterCommand::REBOOT, [], 'owner@example.test');
        $router->queueCommand(RouterCommand::KICK_USER, ['username' => 'TN12345']);
        $router->queueCommand(RouterCommand::KICK_ALL);

        $first = $this->get('/api/agent/trinet_agent_tok/poll')->getContent();

        $lines = array_map('trim', explode("\n", $first));
        $this->assertContains('reboot', $lines);
        $this->assertContains('kick TN12345', $lines);
        $this->assertContains('kick_all', $lines);

        $second = $this->get('/api/agent/trinet_agent_tok/poll')->getContent();
        $this->assertNotContains('reboot', array_map('trim', explode("\n", $second)), 'a command must never run twice');

        $this->assertSame(3, RouterCommand::withoutGlobalScopes()->where('status', 'delivered')->count());
    }

    public function test_a_command_value_cannot_inject_extra_commands(): void
    {
        $router = $this->radiusRouter($this->makeTenant());
        $router->queueCommand(RouterCommand::KICK_USER, ['username' => "x\"]\n/system reset-configuration\n"]);
        $router->queueCommand(RouterCommand::KICK_USER, ['username' => 'TN OK 1']);
        $router->queueCommand(RouterCommand::KICK_USER, ['username' => str_repeat('A', 65)]);
        $router->queueCommand(RouterCommand::KICK_USER, ['username' => 'TNGOOD1']);

        $body = $this->get('/api/agent/trinet_agent_tok/poll')->getContent();

        // Whatever a value contains, every line the router receives is one of a few fixed shapes.
        foreach (explode("\n", trim($body)) as $line) {
            $this->assertMatchesRegularExpression('/^(#.*|reboot|kick_all|kick [A-Za-z0-9:_.\-]{1,64})$/', $line, 'unexpected line: ' . $line);
        }

        $this->assertStringContainsString('kick TNGOOD1', $body);
        $this->assertStringNotContainsString('reset-configuration', $body);
    }

    public function test_the_router_never_runs_text_it_downloaded_it_only_reads_fixed_commands_from_it(): void
    {
        $source = app(\App\Services\ProvisioningScript::class)->agentSource($this->radiusRouter($this->makeTenant()));

        $this->assertStringNotContainsString('/import', $source, 'a tampered reply must not be executable');
        $this->assertStringContainsString('trinetpay-cmd.txt', $source);
        $this->assertStringContainsString('"reboot"', $source);
        $this->assertStringContainsString('"kick_all"', $source);
        // The name of a customer to disconnect is checked on the router before use.
        $this->assertStringContainsString('A-Za-z0-9:_.-', $source);
    }

    public function test_commands_only_go_to_the_router_they_were_meant_for(): void
    {
        $tenant = $this->makeTenant();
        $mine   = $this->radiusRouter($tenant);
        $other  = $this->radiusRouter($tenant, ['nas_identifier' => 'nas-9-other', 'provision_token' => 'p2', 'agent_token' => 'trinet_agent_2']);
        $other->queueCommand(RouterCommand::REBOOT);

        $this->assertNotContains('reboot', array_map('trim', explode("\n", $this->get('/api/agent/trinet_agent_tok/poll')->assertOk()->getContent())));
        $this->assertContains('reboot', array_map('trim', explode("\n", $this->get('/api/agent/trinet_agent_2/poll')->assertOk()->getContent())));
    }

    public function test_a_router_that_polls_too_often_is_slowed_down(): void
    {
        $this->radiusRouter($this->makeTenant());

        for ($i = 0; $i < 30; $i++) {
            $this->get('/api/agent/trinet_agent_tok/poll')->assertOk();
        }

        $this->get('/api/agent/trinet_agent_tok/poll')->assertStatus(429);
    }

    public function test_the_heartbeat_marks_quiet_routers_offline_and_never_dials_out_to_them(): void
    {
        $tenant = $this->makeTenant();
        $quiet  = $this->radiusRouter($tenant, ['nas_identifier' => 'nas-1-quiet', 'provision_token' => 'p1', 'agent_token' => 'a1', 'status' => 'online', 'last_seen_at' => now()->subMinutes(30)]);
        $alive  = $this->radiusRouter($tenant, ['nas_identifier' => 'nas-1-alive', 'provision_token' => 'p2', 'agent_token' => 'a2', 'status' => 'offline', 'last_seen_at' => now()->subSeconds(30)]);
        $new    = $this->radiusRouter($tenant, ['nas_identifier' => 'nas-1-new', 'provision_token' => 'p3', 'agent_token' => 'a3']);

        $this->artisan('router:heartbeat')->assertSuccessful();

        $this->assertSame('offline', $quiet->fresh()->status);
        $this->assertSame('online', $alive->fresh()->status);
        $this->assertSame('unknown', $new->fresh()->status);
        Http::assertNothingSent();
    }

    // ── RADIUS access rows ───────────────────────────────────────────────────

    public function test_granting_access_writes_the_login_the_conditions_and_the_limits(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['speed_down_mbps' => 5, 'speed_up_mbps' => 2]);
        $expires = Carbon::create(2026, 9, 20, 10, 30, 0, 'Africa/Dar_es_Salaam');

        app(RadiusAccess::class)->grant($tenant->id, $package, 'TNABC123', 'TNABC123', $expires, 'test');

        $check = $this->radcheck('TNABC123');
        $this->assertSame([':=', 'TNABC123'], $check['Cleartext-Password']);
        $this->assertSame([':=', 'Sep 20 2026 07:30:00'], $check['Expiration'], 'stored in UTC, the RADIUS server clock');
        $this->assertSame(['=~', '^nas-' . $tenant->id . '-'], $check['NAS-Identifier'], 'ties the login to this ISP routers only');

        $reply = $this->radreply('TNABC123');
        $this->assertSame('2M/5M', $reply['Mikrotik-Rate-Limit'], 'upload first, then download');
        $this->assertSame('300', $reply['Acct-Interim-Interval']);
        $this->assertArrayNotHasKey('Mikrotik-Total-Limit', $reply);
    }

    public function test_a_data_cap_becomes_the_router_byte_limit_including_the_gigaword_part(): void
    {
        $tenant  = $this->makeTenant();
        $small   = $this->makePackage($tenant, ['name' => 'Small', 'data_cap_mb' => 500]);
        $big     = $this->makePackage($tenant, ['name' => 'Big', 'data_cap_mb' => 5000]);

        app(RadiusAccess::class)->grant($tenant->id, $small, 'SMALL1', 'SMALL1', now()->addDay(), 'test');
        app(RadiusAccess::class)->grant($tenant->id, $big, 'BIG1', 'BIG1', now()->addDay(), 'test');

        $this->assertSame((string) (500 * 1024 * 1024), $this->radreply('SMALL1')['Mikrotik-Total-Limit']);
        $this->assertArrayNotHasKey('Mikrotik-Total-Limit-Gigawords', $this->radreply('SMALL1'));

        $bytes = 5000 * 1024 * 1024;
        $this->assertSame((string) ($bytes % 4294967296), $this->radreply('BIG1')['Mikrotik-Total-Limit']);
        $this->assertSame('1', $this->radreply('BIG1')['Mikrotik-Total-Limit-Gigawords']);
    }

    public function test_a_returning_device_gets_its_own_login_by_mac_address(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);

        app(RadiusAccess::class)->grant($tenant->id, $package, 'TNABC123', 'TNABC123', now()->addDay(), 'txn:1', 'aa-bb-cc-dd-ee-ff');

        $mac = $this->radcheck('AA:BB:CC:DD:EE:FF');
        $this->assertSame([':=', 'Accept'], $mac['Auth-Type']);
        $this->assertSame(['=~', '^nas-' . $tenant->id . '-'], $mac['NAS-Identifier'], 'a MAC login is still tied to this ISP');
        $this->assertArrayHasKey('Expiration', $mac);
        $this->assertArrayHasKey('Mikrotik-Rate-Limit', $this->radreply('AA:BB:CC:DD:EE:FF'));
    }

    public function test_an_invalid_mac_address_is_ignored(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);

        app(RadiusAccess::class)->grant($tenant->id, $package, 'TNABC123', 'TNABC123', now()->addDay(), 'txn:1', 'not-a-mac');

        $this->assertSame(['TNABC123'], DB::table('radcheck')->distinct()->pluck('username')->all());
    }

    public function test_granting_again_replaces_the_old_login_instead_of_piling_up_rows(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $radius  = app(RadiusAccess::class);

        $radius->grant($tenant->id, $package, 'TNABC123', 'first', now()->addDay(), 'test');
        $radius->grant($tenant->id, $package, 'TNABC123', 'second', now()->addDays(2), 'test');

        $this->assertSame(1, DB::table('radcheck')->where('username', 'TNABC123')->where('attribute', 'Cleartext-Password')->count());
        $this->assertSame([':=', 'second'], $this->radcheck('TNABC123')['Cleartext-Password']);
    }

    public function test_revoking_removes_every_row_of_a_login(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $radius  = app(RadiusAccess::class);

        $radius->grant($tenant->id, $package, 'TNABC123', 'TNABC123', now()->addDay(), 'test');
        $radius->revoke('TNABC123');

        $this->assertSame(0, DB::table('radcheck')->count());
        $this->assertSame(0, DB::table('radreply')->count());
    }

    public function test_suspending_an_isp_blocks_only_its_own_logins_and_resuming_lifts_it(): void
    {
        $a       = $this->makeTenant('alpha');
        $b       = $this->makeTenant('bravo');
        $radius  = app(RadiusAccess::class);
        $radius->grant($a->id, $this->makePackage($a), 'ALPHA1', 'ALPHA1', now()->addDay(), 'test');
        $radius->grant($a->id, $this->makePackage($a, ['name' => 'Two']), 'ALPHA2', 'ALPHA2', now()->addDay(), 'test');
        $radius->grant($b->id, $this->makePackage($b), 'BRAVO1', 'BRAVO1', now()->addDay(), 'test');

        $radius->suspendTenant($a->id);
        $radius->suspendTenant($a->id);   // doing it twice must not add duplicate rows

        $this->assertSame([':=', 'Reject'], $this->radcheck('ALPHA1')['Auth-Type']);
        $this->assertSame([':=', 'Reject'], $this->radcheck('ALPHA2')['Auth-Type']);
        $this->assertArrayNotHasKey('Auth-Type', $this->radcheck('BRAVO1'));
        $this->assertSame(1, DB::table('radcheck')->where('username', 'ALPHA1')->where('attribute', 'Auth-Type')->count());

        $radius->resumeTenant($a->id);

        $this->assertArrayNotHasKey('Auth-Type', $this->radcheck('ALPHA1'));
        $this->assertArrayHasKey('Cleartext-Password', $this->radcheck('ALPHA1'), 'customers keep their remaining time');
    }

    public function test_expired_logins_are_pruned_after_a_day(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $radius  = app(RadiusAccess::class);

        $radius->grant($tenant->id, $package, 'OLD1', 'OLD1', now()->subDays(3), 'test');
        $radius->grant($tenant->id, $package, 'RECENT1', 'RECENT1', now()->subHours(2), 'test');
        $radius->grant($tenant->id, $package, 'LIVE1', 'LIVE1', now()->addDay(), 'test');

        $this->assertSame(1, $radius->pruneExpired());
        $this->assertSame(['LIVE1', 'RECENT1'], DB::table('radcheck')->distinct()->orderBy('username')->pluck('username')->all());
    }

    // ── Payment and voucher through RADIUS ───────────────────────────────────

    public function test_a_payment_on_a_one_command_router_grants_access_in_the_same_transaction_without_calling_the_router(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['price' => 5000]);
        $this->radiusRouter($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1', ['customer_mac' => 'AA:BB:CC:DD:EE:FF']);
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);

        app(PaymentSettlement::class)->verifyAndSettle($payment);

        $payment->refresh();
        $token = $payment->voucher_code;

        $this->assertSame('completed', $payment->status);
        $this->assertSame('done', $payment->provision_status);
        $this->assertSame([':=', $token], $this->radcheck($token)['Cleartext-Password']);
        $this->assertArrayHasKey('Auth-Type', $this->radcheck('AA:BB:CC:DD:EE:FF'));
        $this->assertSame(0, $this->hotspotUsersCreated(), 'no call to the router was needed');
        $this->assertSame(5000, TenantWallet::withoutGlobalScopes()->value('balance'));
    }

    public function test_if_granting_access_fails_the_payment_is_not_settled_and_can_be_retried(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->radiusRouter($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);

        $this->mock(RadiusAccess::class, function ($mock) {
            $mock->shouldReceive('grant')->andThrow(new RuntimeException('database hiccup'));
        });

        try {
            app(PaymentSettlement::class)->verifyAndSettle($payment);
            $this->fail('the failure must reach the caller so the gateway retries the callback');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame('pending', $payment->fresh()->status, 'money and access are settled together or not at all');
        $this->assertSame(0, TenantWallet::withoutGlobalScopes()->count());
    }

    public function test_redeeming_a_voucher_on_a_one_command_router_never_needs_the_router(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->radiusRouter($tenant);
        $voucher = Voucher::create(['tenant_id' => $tenant->id, 'package_id' => $package->id, 'code' => 'TNVOUCH123']);
        $this->fakeExternalServices([], routerOk: false);   // even a dead router changes nothing

        $this->postJson('/api/voucher/redeem?tenant=acme', ['code' => 'tnvouch123', 'mac' => 'aa:bb:cc:dd:ee:ff'])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertNotNull($voucher->fresh()->used_at);
        $this->assertSame([':=', 'TNVOUCH123'], $this->radcheck('TNVOUCH123')['Cleartext-Password']);
        $this->assertArrayHasKey('Auth-Type', $this->radcheck('AA:BB:CC:DD:EE:FF'));
        $this->assertSame(0, $this->hotspotUsersCreated());
        $this->assertSame(Transaction::CHANNEL_VOUCHER, Transaction::firstOrFail()->channel);
    }

    // ── Suspension ───────────────────────────────────────────────────────────

    public function test_suspending_an_isp_from_the_admin_panel_cuts_customers_off_at_once(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $router  = $this->radiusRouter($tenant);
        $apiRouter = $this->makeRouter($tenant, ['nas_identifier' => 'nas-1-old', 'provision_token' => 'old']);
        app(RadiusAccess::class)->grant($tenant->id, $package, 'TNLIVE1', 'TNLIVE1', now()->addDay(), 'test');
        $this->actingAs($this->makeAdmin(), 'admin');

        $this->post("/admin/tenants/{$tenant->id}/suspend")->assertRedirect();

        $this->assertSame('suspended', $tenant->fresh()->status);
        $this->assertSame([':=', 'Reject'], $this->radcheck('TNLIVE1')['Auth-Type']);
        $this->assertSame(1, RouterCommand::withoutGlobalScopes()->where('router_id', $router->id)->where('type', 'kick_all')->count());
        $this->assertSame(0, RouterCommand::withoutGlobalScopes()->where('router_id', $apiRouter->id)->count());

        $this->post("/admin/tenants/{$tenant->id}/activate")->assertRedirect();

        $this->assertSame('active', $tenant->fresh()->status);
        $this->assertArrayNotHasKey('Auth-Type', $this->radcheck('TNLIVE1'));
    }

    // ── Dashboard: adding and managing routers ───────────────────────────────

    public function test_a_new_router_only_needs_a_name_and_connects_out(): void
    {
        $tenant = $this->makeTenant('testisp');
        $owner  = $this->makeOwner($tenant);

        $response = $this->actingAs($owner, 'tenant')->post('/dashboard/routers', ['name' => 'Office router']);

        $router = TenantRouter::withoutGlobalScopes()->firstOrFail();
        $response->assertRedirect(route('dashboard.routers.edit', $router));

        $this->assertSame('radius', $router->auth_mode);
        $this->assertNull($router->router_ip);
        $this->assertStringStartsWith('nas-' . $tenant->id . '-', $router->nas_identifier);
        $this->assertStringStartsWith('trinet_prov_', $router->provision_token);
        $this->assertStringStartsWith('trinet_agent_', $router->agent_token);

        $this->get(route('dashboard.routers.edit', $router))
            ->assertOk()
            ->assertSee('trinetpay-bootstrap.rsc')
            ->assertSee('/provision/' . $router->provision_token, false);
    }

    public function test_the_older_api_connection_is_still_available_and_still_checks_the_address(): void
    {
        $tenant = $this->makeTenant();
        $owner  = $this->makeOwner($tenant);
        $this->actingAs($owner, 'tenant');

        $this->post('/dashboard/routers', ['mode' => 'api', 'name' => 'Old', 'router_ip' => '8.8.8.8', 'username' => 'u', 'password' => 'p'])
            ->assertSessionHasErrors('router_ip');

        $this->post('/dashboard/routers', ['mode' => 'api', 'name' => 'Old', 'router_ip' => '192.168.88.1', 'username' => 'u', 'password' => 'p'])
            ->assertSessionHasNoErrors();

        $this->assertSame('api', TenantRouter::withoutGlobalScopes()->firstOrFail()->auth_mode);
    }

    public function test_a_blank_api_login_is_created_from_the_tenant_name_with_a_random_password(): void
    {
        $tenant = $this->makeTenant('juma', ['name' => 'Juma WiFi']);
        $this->actingAs($this->makeOwner($tenant), 'tenant');

        $this->post('/dashboard/routers', ['mode' => 'api', 'name' => 'Shop', 'router_ip' => '192.168.88.1'])
            ->assertSessionHasNoErrors();

        $router = TenantRouter::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('tn_juma_wifi', $router->username);
        $this->assertSame(24, strlen($router->password));

        $script = $this->get('/provision/' . $router->provision_token)->getContent();
        $this->assertStringContainsString('name="tn_juma_wifi"', $script);
    }

    public function test_an_owner_cannot_manage_another_tenants_router(): void
    {
        $mine    = $this->makeTenant('alpha');
        $theirs  = $this->makeTenant('bravo');
        $foreign = $this->radiusRouter($theirs);
        $this->actingAs($this->makeOwner($mine), 'tenant');

        foreach ([
            fn () => $this->get("/dashboard/routers/{$foreign->id}/edit"),
            fn () => $this->post("/dashboard/routers/{$foreign->id}/command", ['type' => 'reboot']),
            fn () => $this->post("/dashboard/routers/{$foreign->id}/rotate"),
            fn () => $this->post("/dashboard/routers/{$foreign->id}/switch"),
            fn () => $this->delete("/dashboard/routers/{$foreign->id}"),
        ] as $attempt) {
            $this->assertContains($attempt()->getStatusCode(), [403, 404]);
        }

        $this->assertSame(0, RouterCommand::withoutGlobalScopes()->count());
        $this->assertNotNull(TenantRouter::withoutGlobalScopes()->find($foreign->id));
    }

    public function test_an_owner_can_reboot_or_disconnect_everyone_but_only_on_one_command_routers(): void
    {
        $tenant = $this->makeTenant();
        $router = $this->radiusRouter($tenant);
        $old    = $this->makeRouter($tenant, ['nas_identifier' => 'nas-1-old', 'provision_token' => 'old']);
        $this->actingAs($this->makeOwner($tenant), 'tenant');

        $this->post("/dashboard/routers/{$router->id}/command", ['type' => 'reboot'])->assertSessionHas('success');
        $this->post("/dashboard/routers/{$router->id}/command", ['type' => 'kick_all'])->assertSessionHas('success');
        $this->post("/dashboard/routers/{$router->id}/command", ['type' => 'format-disk'])->assertSessionHasErrors('type');
        $this->post("/dashboard/routers/{$old->id}/command", ['type' => 'reboot'])->assertStatus(422);

        $commands = RouterCommand::withoutGlobalScopes()->orderBy('id')->get();
        $this->assertSame(['reboot', 'kick_all'], $commands->pluck('type')->all());
        $this->assertSame('owner@example.test', $commands[0]->requested_by);
    }

    public function test_switching_and_rotating_issue_new_secrets_and_require_a_new_setup(): void
    {
        $tenant = $this->makeTenant();
        $old    = $this->makeRouter($tenant, ['provision_token' => 'old_tok']);
        $this->actingAs($this->makeOwner($tenant), 'tenant');

        $this->post("/dashboard/routers/{$old->id}/switch")->assertRedirect();
        $old->refresh();
        $this->assertSame('radius', $old->auth_mode);
        $this->assertNotSame('old_tok', $old->provision_token);
        $this->assertStringStartsWith('trinet_agent_', $old->agent_token);

        $provision = $old->provision_token;
        $agent     = $old->agent_token;
        $old->update(['provision_status' => 'completed']);

        $this->post("/dashboard/routers/{$old->id}/rotate")->assertRedirect();
        $old->refresh();
        $this->assertNotSame($provision, $old->provision_token);
        $this->assertNotSame($agent, $old->agent_token);
        $this->assertSame('pending', $old->provision_status);

        $this->get('/api/agent/' . $agent . '/poll')->assertNotFound();
    }

    public function test_the_router_list_renders_for_both_kinds_of_router(): void
    {
        $tenant = $this->makeTenant();
        $this->radiusRouter($tenant, ['name' => 'Fresh router']);
        $this->makeRouter($tenant, ['name' => 'Legacy router', 'nas_identifier' => 'nas-1-old', 'provision_token' => 'old']);

        $this->actingAs($this->makeOwner($tenant), 'tenant')
            ->get('/dashboard/routers')
            ->assertOk()
            ->assertSee('Fresh router')
            ->assertSee('Legacy router')
            ->assertSee('Not connected');
    }

    public function test_onboarding_creates_a_one_command_router_and_shows_the_command(): void
    {
        $tenant = $this->makeTenant('testisp', ['status' => 'onboarding']);
        $owner  = $this->makeOwner($tenant);

        $this->actingAs($owner, 'tenant')
            ->get('/onboarding/router')
            ->assertOk()
            ->assertSee('trinetpay-bootstrap.rsc');

        $router = TenantRouter::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('radius', $router->auth_mode);
        $this->assertNull($router->username);
    }
}
