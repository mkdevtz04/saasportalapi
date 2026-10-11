<?php

namespace Tests\Feature;

use App\Contracts\SmsGateway;
use App\Models\AuditLog;
use App\Models\RouterCommand;
use App\Models\TenantRouter;
use App\Models\Transaction;
use App\Models\WalletEntry;
use App\Models\WithdrawalRequest;
use App\Notifications\RouterStatusNotification;
use App\Services\Radius\RadiusAccess;
use App\Services\Radius\RadiusReports;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function captureSms(): object
    {
        $fake = new class implements SmsGateway {
            public array $sent = [];

            public function send(string $to, string $message): bool
            {
                $this->sent[] = ['to' => $to, 'message' => $message];

                return true;
            }
        };

        $this->app->instance(SmsGateway::class, $fake);

        return $fake;
    }

    private function radiusRouter($tenant, array $overrides = []): TenantRouter
    {
        return $this->makeRouter($tenant, array_merge([
            'auth_mode'        => 'radius',
            'router_ip'        => null,
            'username'         => null,
            'password'         => null,
            'nas_identifier'   => 'nas-' . $tenant->id . '-abc12345',
            'provision_token'  => 'prov-' . $tenant->id,
            'agent_token'      => 'agent-' . $tenant->id,
            'provision_status' => 'completed',
        ], $overrides));
    }

    private function addSession(string $username, array $overrides = []): void
    {
        DB::table('radacct')->insert(array_merge([
            'acctsessionid'    => 'S' . uniqid(),
            'acctuniqueid'     => md5(uniqid('', true)),
            'username'         => $username,
            'nasipaddress'     => '203.0.113.5',
            'acctstarttime'    => now()->utc()->subMinutes(30)->format('Y-m-d H:i:s'),
            'acctupdatetime'   => now()->utc()->subMinutes(2)->format('Y-m-d H:i:s'),
            'acctstoptime'     => null,
            'acctsessiontime'  => 1800,
            'acctinputoctets'  => 1024 * 1024,
            'acctoutputoctets' => 20 * 1024 * 1024,
            'callingstationid' => 'AA:BB:CC:DD:EE:FF',
            'framedipaddress'  => '10.5.50.20',
        ], $overrides));
    }

    // ── Formatting helpers ───────────────────────────────────────────────────

    public function test_sizes_and_durations_read_naturally(): void
    {
        $this->assertSame('0 B', Format::bytes(0));
        $this->assertSame('1.5 KB', Format::bytes(1536));
        $this->assertSame('20 MB', Format::bytes(20 * 1024 * 1024));
        $this->assertSame('5 GB', Format::bytes(5 * 1024 ** 3));
        $this->assertSame('45s', Format::duration(45));
        $this->assertSame('1h 2m', Format::duration(3725));
        $this->assertSame('1d 2h', Format::duration(93600));
    }

    public function test_spreadsheet_cells_that_look_like_formulas_are_defused(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://evil\")", Csv::cell('=HYPERLINK("http://evil")'));
        $this->assertSame("'+1234", Csv::cell('+1234'));
        $this->assertSame("'@SUM(A1)", Csv::cell('@SUM(A1)'));
        $this->assertSame('Daily', Csv::cell('Daily'));
        $this->assertSame('5000', Csv::cell(5000));
    }

    // ── Live sessions and usage ──────────────────────────────────────────────

    public function test_only_open_recent_sessions_of_the_tenants_own_customers_count_as_online(): void
    {
        $a = $this->makeTenant('alpha');
        $b = $this->makeTenant('bravo');
        $radius = app(RadiusAccess::class);
        $radius->grant($a->id, $this->makePackage($a), 'TNALPHA1', 'x', now()->addDay(), 'txn:1');
        $radius->grant($b->id, $this->makePackage($b), 'TNBRAVO1', 'x', now()->addDay(), 'txn:2');

        $this->addSession('TNALPHA1');                                                    // online
        $this->addSession('TNALPHA1', ['acctupdatetime' => now()->utc()->subMinutes(40)->format('Y-m-d H:i:s')]);   // silent for too long
        $this->addSession('TNALPHA1', ['acctstoptime' => now()->utc()->subMinutes(5)->format('Y-m-d H:i:s')]);      // ended
        $this->addSession('TNBRAVO1');                                                    // another ISP
        $this->addSession('UNKNOWN1');                                                    // nobody's login

        $online = app(RadiusReports::class)->activeSessions($a->id);

        $this->assertCount(1, $online);
        $this->assertSame('TNALPHA1', $online[0]->username);
    }

    public function test_usage_is_summed_and_grouped_by_east_africa_day(): void
    {
        $a = $this->makeTenant('alpha');
        app(RadiusAccess::class)->grant($a->id, $this->makePackage($a), 'TNALPHA1', 'x', now()->addDay(), 'txn:1');

        // 00:30 East Africa Time today is 21:30 UTC yesterday, and must count as today.
        $startOfToday = now('Africa/Dar_es_Salaam')->startOfDay()->utc();
        $this->addSession('TNALPHA1', ['acctstarttime' => $startOfToday->copy()->addMinutes(30)->format('Y-m-d H:i:s'), 'acctinputoctets' => 100, 'acctoutputoctets' => 900]);
        $this->addSession('TNALPHA1', ['acctstarttime' => $startOfToday->copy()->subHours(2)->format('Y-m-d H:i:s'), 'acctinputoctets' => 10, 'acctoutputoctets' => 90]);

        $reports = app(RadiusReports::class);

        $today = $reports->usageSince($a->id, $startOfToday);
        $this->assertSame(['download' => 900, 'upload' => 100, 'sessions' => 1], $today);

        $byDay = $reports->usageByDay($a->id, 7);
        $this->assertCount(7, $byDay);
        $this->assertSame(1000, $byDay[now('Africa/Dar_es_Salaam')->format('Y-m-d')]);
        $this->assertSame(100, $byDay[now('Africa/Dar_es_Salaam')->subDay()->format('Y-m-d')]);
    }

    public function test_the_sessions_page_shows_only_the_owners_customers(): void
    {
        $a = $this->makeTenant('alpha');
        $b = $this->makeTenant('bravo');
        $this->radiusRouter($a);
        $radius = app(RadiusAccess::class);
        $radius->grant($a->id, $this->makePackage($a), 'TNALPHA1', 'x', now()->addDay(), 'txn:1');
        $radius->grant($b->id, $this->makePackage($b), 'TNBRAVO1', 'x', now()->addDay(), 'txn:2');
        $this->addSession('TNALPHA1');
        $this->addSession('TNBRAVO1');

        $this->actingAs($this->makeOwner($a), 'tenant')
            ->get('/dashboard/sessions')
            ->assertOk()
            ->assertSee('TNALPHA1')
            ->assertDontSee('TNBRAVO1')
            ->assertSee('20 MB');
    }

    public function test_the_sessions_page_explains_itself_when_no_router_is_connected_yet(): void
    {
        $tenant = $this->makeTenant();

        $this->actingAs($this->makeOwner($tenant), 'tenant')
            ->get('/dashboard/sessions')
            ->assertOk()
            ->assertSee('one-command setup');
    }

    public function test_disconnecting_a_customer_removes_the_code_and_device_logins_and_kicks_the_session(): void
    {
        $tenant  = $this->makeTenant();
        $router  = $this->radiusRouter($tenant);
        $package = $this->makePackage($tenant);
        $radius  = app(RadiusAccess::class);
        $radius->grant($tenant->id, $package, 'TNCUST1', 'TNCUST1', now()->addDay(), 'txn:5', 'AA:BB:CC:DD:EE:FF');
        $radius->grant($tenant->id, $package, 'TNOTHER1', 'TNOTHER1', now()->addDay(), 'txn:6');

        $this->actingAs($this->makeOwner($tenant), 'tenant')
            ->post('/dashboard/sessions/disconnect', ['username' => 'TNCUST1'])
            ->assertSessionHas('success');

        $this->assertSame(0, DB::table('radcheck')->whereIn('username', ['TNCUST1', 'AA:BB:CC:DD:EE:FF'])->count());
        $this->assertGreaterThan(0, DB::table('radcheck')->where('username', 'TNOTHER1')->count(), 'another customer is untouched');

        $kicked = RouterCommand::withoutGlobalScopes()->where('router_id', $router->id)->where('type', 'kick_user')->get();
        $this->assertEqualsCanonicalizing(['TNCUST1', 'AA:BB:CC:DD:EE:FF'], $kicked->pluck('payload.username')->all());
        $this->assertSame(1, AuditLog::where('action', 'session.revoked')->count());
    }

    public function test_an_owner_cannot_disconnect_another_isps_customer(): void
    {
        $mine   = $this->makeTenant('alpha');
        $theirs = $this->makeTenant('bravo');
        app(RadiusAccess::class)->grant($theirs->id, $this->makePackage($theirs), 'TNTHEIRS1', 'x', now()->addDay(), 'txn:1');

        $this->actingAs($this->makeOwner($mine), 'tenant')
            ->post('/dashboard/sessions/disconnect', ['username' => 'TNTHEIRS1'])
            ->assertNotFound();

        $this->assertGreaterThan(0, DB::table('radcheck')->where('username', 'TNTHEIRS1')->count());
    }

    // ── Reports ──────────────────────────────────────────────────────────────

    private function sale($tenant, $package, $router, array $overrides = []): Transaction
    {
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $sale = Transaction::create(array_merge([
            'tenant_id' => $tenant->id, 'router_id' => $router?->id, 'package_id' => $package->id,
            'phone' => '0712345678', 'amount' => $package->price, 'status' => 'completed',
            'channel' => 'portal', 'voucher_code' => 'TN' . strtoupper(substr(md5(uniqid()), 0, 8)),
        ], $overrides));

        // created_at is not mass assignable, so set it directly.
        if ($createdAt !== null) {
            Transaction::withoutGlobalScopes()->whereKey($sale->id)->update(['created_at' => $createdAt]);
        }

        return $sale->refresh();
    }

    public function test_the_report_splits_sales_by_type_package_router_and_east_africa_day(): void
    {
        $tenant = $this->makeTenant();
        $daily  = $this->makePackage($tenant, ['name' => 'Daily', 'price' => 5000]);
        $weekly = $this->makePackage($tenant, ['name' => 'Weekly', 'price' => 20000]);
        $router = $this->radiusRouter($tenant, ['name' => 'Kariakoo']);

        $this->sale($tenant, $daily, $router, ['created_at' => '2026-09-15 10:00:00']);
        $this->sale($tenant, $daily, $router, ['created_at' => '2026-09-15 11:00:00']);
        $this->sale($tenant, $weekly, $router, ['created_at' => '2026-09-16 10:00:00', 'channel' => 'voucher']);
        // 21:30 UTC on the 16th is 00:30 on the 17th in East Africa
        $this->sale($tenant, $daily, $router, ['created_at' => '2026-09-16 21:30:00']);
        $this->sale($tenant, $daily, $router, ['created_at' => '2026-08-01 10:00:00']);                    // outside the range
        $this->sale($tenant, $daily, $router, ['created_at' => '2026-09-15 12:00:00', 'status' => 'failed']); // never paid

        $response = $this->actingAs($this->makeOwner($tenant), 'tenant')
            ->get('/dashboard/reports?from=2026-09-01&to=2026-09-30')
            ->assertOk();

        $data = $response->viewData('totals');
        $this->assertSame(15000, $data['portal']);
        $this->assertSame(3, $data['portal_count']);
        $this->assertSame(20000, $data['voucher']);

        $byDay = $response->viewData('byDay');
        $this->assertSame(10000, $byDay['2026-09-15']['portal']);
        $this->assertSame(20000, $byDay['2026-09-16']['voucher']);
        $this->assertSame(5000, $byDay['2026-09-17']['portal'], 'sold at 00:30 East Africa Time on the 17th');

        $this->assertSame(['count' => 3, 'amount' => 15000], $response->viewData('byPackage')['Daily']);
        $this->assertSame(['count' => 4, 'amount' => 35000], $response->viewData('byRouter')['Kariakoo']);
    }

    public function test_the_report_never_includes_another_isps_sales(): void
    {
        $mine   = $this->makeTenant('alpha');
        $theirs = $this->makeTenant('bravo');
        $this->sale($theirs, $this->makePackage($theirs, ['price' => 99999]), null, ['created_at' => now()]);
        $this->sale($mine, $this->makePackage($mine, ['price' => 1000]), null, ['created_at' => now()]);

        $response = $this->actingAs($this->makeOwner($mine), 'tenant')->get('/dashboard/reports')->assertOk();

        $this->assertSame(1000, $response->viewData('totals')['portal']);
    }

    public function test_the_report_range_is_validated_and_swapped_when_backwards(): void
    {
        $tenant = $this->makeTenant();
        $this->actingAs($this->makeOwner($tenant), 'tenant');

        $this->get('/dashboard/reports?from=nonsense')->assertSessionHasErrors('from');

        $response = $this->get('/dashboard/reports?from=2026-09-20&to=2026-09-10')->assertOk();
        $this->assertSame('2026-09-10', $response->viewData('from')->format('Y-m-d'));
    }

    public function test_the_spreadsheet_lists_own_sales_and_defuses_formulas(): void
    {
        $mine    = $this->makeTenant('alpha');
        $theirs  = $this->makeTenant('bravo');
        $package = $this->makePackage($mine, ['name' => 'Daily']);
        $this->sale($mine, $package, null, ['phone' => '=cmd|calc', 'voucher_code' => 'TNMINE1', 'created_at' => now()]);
        $this->sale($theirs, $this->makePackage($theirs), null, ['voucher_code' => 'TNTHEIRS1', 'created_at' => now()]);

        $response = $this->actingAs($this->makeOwner($mine), 'tenant')->get('/dashboard/reports/export');

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Date (EAT)",Reference,Type', $csv);
        $this->assertStringContainsString('TNMINE1', $csv);
        $this->assertStringNotContainsString('TNTHEIRS1', $csv);
        $this->assertStringContainsString("'=cmd|calc", $csv);
        $this->assertStringNotContainsString(',=cmd|calc', $csv);
    }

    public function test_the_report_counts_withdrawals_paid_in_the_period_with_their_fees(): void
    {
        $tenant = $this->makeTenant();
        WithdrawalRequest::create([
            'tenant_id' => $tenant->id, 'amount' => 10000, 'fee_amount' => 500, 'net_amount' => 9500,
            'mobile_number' => '0712345678', 'status' => 'paid', 'processed_at' => now(),
        ]);
        WithdrawalRequest::create([
            'tenant_id' => $tenant->id, 'amount' => 8000, 'fee_amount' => 400, 'net_amount' => 7600,
            'mobile_number' => '0712345678', 'status' => 'pending',
        ]);

        $withdrawals = $this->actingAs($this->makeOwner($tenant), 'tenant')->get('/dashboard/reports')->viewData('withdrawals');

        $this->assertSame(1, (int) $withdrawals->count);
        $this->assertSame(9500, (int) $withdrawals->net);
        $this->assertSame(500, (int) $withdrawals->fees);
    }

    // ── Router alerts ────────────────────────────────────────────────────────

    public function test_the_owner_is_told_once_when_a_router_goes_quiet_and_once_when_it_returns(): void
    {
        Notification::fake();
        $sms    = $this->captureSms();
        $tenant = $this->makeTenant();
        $owner  = $this->makeOwner($tenant);
        $owner->update(['phone' => '0712345678']);
        $router = $this->radiusRouter($tenant, ['name' => 'Kariakoo', 'status' => 'online', 'last_seen_at' => now()->subMinutes(30)]);

        $this->artisan('router:heartbeat')->assertSuccessful();
        $this->artisan('router:heartbeat')->assertSuccessful();

        Notification::assertSentToTimes($owner, RouterStatusNotification::class, 1);
        $this->assertCount(1, $sms->sent);
        $this->assertSame('255712345678', $sms->sent[0]['to']);
        $this->assertStringContainsString('Kariakoo is offline', $sms->sent[0]['message']);
        $this->assertNotNull($router->fresh()->offline_alerted_at);

        // The router comes back.
        $this->get('/api/agent/agent-' . $tenant->id . '/poll')->assertOk();
        $this->artisan('router:heartbeat')->assertSuccessful();

        Notification::assertSentToTimes($owner, RouterStatusNotification::class, 2);
        $this->assertCount(2, $sms->sent);
        $this->assertStringContainsString('back online', $sms->sent[1]['message']);
        $this->assertNull($router->fresh()->offline_alerted_at);
    }

    public function test_a_short_blip_and_unfinished_setups_raise_no_alarm(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();
        $this->makeOwner($tenant);
        // Silent for 7 minutes: shown offline, but not long enough to alarm anyone.
        $this->radiusRouter($tenant, ['nas_identifier' => 'nas-1-a', 'provision_token' => 'p1', 'agent_token' => 'a1', 'status' => 'online', 'last_seen_at' => now()->subMinutes(7)]);
        // Never finished setup.
        $this->radiusRouter($tenant, ['nas_identifier' => 'nas-1-b', 'provision_token' => 'p2', 'agent_token' => 'a2', 'provision_status' => 'pending', 'last_seen_at' => now()->subHours(3)]);
        // Never reported at all.
        $this->radiusRouter($tenant, ['nas_identifier' => 'nas-1-c', 'provision_token' => 'p3', 'agent_token' => 'a3']);
        // Connected the old way, judged by its own check.
        $this->makeRouter($tenant, ['nas_identifier' => 'nas-1-d', 'provision_token' => 'p4', 'status' => 'offline', 'last_seen_at' => now()->subHours(3)]);

        $this->artisan('router:heartbeat');

        Notification::assertNothingSent();
    }

    public function test_the_dashboard_warns_about_offline_routers_and_customers_who_paid_but_are_not_online(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $router  = $this->radiusRouter($tenant, ['name' => 'Kariakoo', 'last_seen_at' => now()->subMinutes(40)]);
        $this->sale($tenant, $package, $router, ['provision_status' => 'failed', 'created_at' => now()]);

        $this->actingAs($this->makeOwner($tenant), 'tenant')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('1 router is offline')
            ->assertSee('Kariakoo')
            ->assertSee('paid but is not online yet');

        $this->get('/dashboard/transactions?access=failed')->assertOk()->assertSee('Needs help');
    }

    // ── Reconciliation ───────────────────────────────────────────────────────

    /** Builds a small, correct history: 15,000 collected, one paid withdrawal, one still open. */
    private function healthyBooks(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['price' => 10000]);
        $wallet  = $this->makeWallet($tenant, 0);

        $this->sale($tenant, $package, null, ['amount' => 10000, 'palmpesa_order_id' => 'ORD-1']);
        $this->sale($tenant, $package, null, ['amount' => 5000, 'palmpesa_order_id' => 'ORD-2']);
        $wallet->credit(10000, WalletEntry::PAYMENT, 'TXN-1');
        $wallet->credit(5000, WalletEntry::PAYMENT, 'TXN-2');

        $paid = WithdrawalRequest::create(['tenant_id' => $tenant->id, 'amount' => 6000, 'fee_amount' => 300, 'net_amount' => 5700, 'mobile_number' => '0712345678', 'status' => 'paid', 'processed_at' => now()]);
        $wallet->debit(6000, WalletEntry::WITHDRAWAL, 'WDR-' . $paid->id);
        $open = WithdrawalRequest::create(['tenant_id' => $tenant->id, 'amount' => 2000, 'fee_amount' => 100, 'net_amount' => 1900, 'mobile_number' => '0712345678', 'status' => 'pending']);
        $wallet->debit(2000, WalletEntry::WITHDRAWAL, 'WDR-' . $open->id);
    }

    public function test_the_platform_totals_show_where_the_money_is(): void
    {
        $this->healthyBooks();

        $platform = app(\App\Services\WalletAuditor::class)->platform();

        $this->assertSame(15000, $platform['collected']);
        $this->assertSame(5700, $platform['paid_out']);
        $this->assertSame(9300, $platform['expected_cash']);
        $this->assertSame(7000, $platform['owed_wallets']);
        $this->assertSame(2000, $platform['owed_open']);
        $this->assertSame(9000, $platform['owed_total']);
        $this->assertSame(300, $platform['platform_money'], 'exactly the fee already earned');
        $this->assertSame(300, $platform['fees_earned']);
        $this->assertSame(100, $platform['fees_pending']);
    }

    public function test_the_reconciliation_page_is_calm_when_the_books_add_up(): void
    {
        $this->healthyBooks();

        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/reconciliation')
            ->assertOk()
            ->assertSee('Every wallet is explained')
            ->assertDontSee('do not add up')
            ->assertSee('9,300');
    }

    public function test_the_reconciliation_page_raises_the_alarm_for_an_unbacked_wallet_and_a_shortfall(): void
    {
        $this->healthyBooks();
        $thief = $this->makeTenant('thief');
        $this->makeWallet($thief, 50000);   // money that no payment explains

        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/reconciliation')
            ->assertOk()
            ->assertSee('1 ISP wallet(s) do not add up')
            ->assertSee('The platform owes more than it collected')
            ->assertSee('UNBACKED');
    }

    public function test_the_palmpesa_list_contains_only_confirmed_portal_payments_with_their_order_ids(): void
    {
        $this->healthyBooks();
        $tenant = $this->makeTenant('other');
        $this->sale($tenant, $this->makePackage($tenant), null, ['status' => 'failed', 'palmpesa_order_id' => 'ORD-FAILED']);
        $this->sale($tenant, $this->makePackage($tenant, ['name' => 'V']), null, ['channel' => 'voucher', 'palmpesa_order_id' => null, 'voucher_code' => 'TNVOUCHER9']);

        $csv = $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/reconciliation/export')->assertOk()->streamedContent();

        $this->assertStringContainsString('ORD-1', $csv);
        $this->assertStringContainsString('ORD-2', $csv);
        $this->assertStringNotContainsString('ORD-FAILED', $csv);
        $this->assertStringNotContainsString('TNVOUCHER9', $csv);
    }

    public function test_only_the_platform_admin_can_see_the_money_pages(): void
    {
        $this->healthyBooks();

        $this->get('/admin/reconciliation')->assertRedirect(route('admin.login'));
        $this->get('/admin/audit')->assertRedirect(route('admin.login'));

        $tenant = $this->makeTenant('spy');
        $this->actingAs($this->makeOwner($tenant), 'tenant')->get('/admin/reconciliation')->assertRedirect(route('admin.login'));
    }

    // ── Audit trail ──────────────────────────────────────────────────────────

    public function test_money_actions_leave_a_trail_without_exposing_full_phone_numbers(): void
    {
        $tenant = $this->makeTenant();
        $owner  = $this->makeOwner($tenant);
        $this->makeWallet($tenant, 20000);

        $this->actingAs($owner, 'tenant')
            ->post('/dashboard/wallet/withdraw', ['amount' => 10000, 'mobile_number' => '0712345678'])
            ->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'withdrawal.requested')->firstOrFail();
        $this->assertSame('tenant', $log->actor_type);
        $this->assertSame('owner@example.test', $log->actor_label);
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertSame(10000, $log->meta['amount']);
        $this->assertSame(500, $log->meta['fee']);
        $this->assertSame('071*****78', $log->meta['to']);
        $this->assertStringNotContainsString('0712345678', json_encode($log->toArray()));
    }

    public function test_the_admin_decisions_on_a_withdrawal_are_recorded(): void
    {
        $tenant = $this->makeTenant();
        $this->makeWallet($tenant, 0, totalEarned: 10000);
        $w = WithdrawalRequest::create(['tenant_id' => $tenant->id, 'amount' => 6000, 'fee_amount' => 300, 'net_amount' => 5700, 'mobile_number' => '0712345678', 'status' => 'pending']);
        $this->actingAs($this->makeAdmin(), 'admin');

        $this->post("/admin/withdrawals/{$w->id}/approve");
        $this->post("/admin/withdrawals/{$w->id}/paid");

        $this->assertSame(['withdrawal.approved', 'withdrawal.paid'], AuditLog::orderBy('id')->pluck('action')->all());
        $this->assertSame('admin', AuditLog::first()->actor_type);
    }

    public function test_suspensions_router_commands_and_payout_number_changes_are_recorded(): void
    {
        $tenant = $this->makeTenant();
        $owner  = $this->makeOwner($tenant);
        $tenant->settings()->create(['brand_color' => '#0b7a75', 'withdrawal_number' => '0712345678']);
        $router = $this->radiusRouter($tenant);

        $this->actingAs($owner, 'tenant');
        $this->post("/dashboard/routers/{$router->id}/command", ['type' => 'reboot']);
        $this->post('/dashboard/settings', ['brand_color' => '#0b7a75', 'default_language' => 'sw', 'withdrawal_number' => '0755999888']);

        $change = AuditLog::where('action', 'settings.payout_number_changed')->firstOrFail();
        $this->assertSame('071*****78', $change->meta['from']);
        $this->assertSame('075*****88', $change->meta['to']);
        $this->assertSame(1, AuditLog::where('action', 'router.reboot')->count());

        // Saving the same number again is not a change.
        $this->post('/dashboard/settings', ['brand_color' => '#0b7a75', 'default_language' => 'sw', 'withdrawal_number' => '0755999888']);
        $this->assertSame(1, AuditLog::where('action', 'settings.payout_number_changed')->count());

        $this->actingAs($this->makeAdmin(), 'admin')->post("/admin/tenants/{$tenant->id}/suspend");
        $this->assertSame(1, AuditLog::where('action', 'tenant.suspended')->count());
    }

    public function test_the_audit_trail_cannot_be_edited_or_deleted(): void
    {
        Audit::record('test.action', null, ['a' => 1]);
        $log = AuditLog::firstOrFail();

        foreach ([fn () => $log->update(['action' => 'x']), fn () => $log->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('the audit trail must be append-only');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_admin_can_filter_the_audit_trail_and_wildcards_are_not_special(): void
    {
        $a = $this->makeTenant('alpha');
        $b = $this->makeTenant('bravo');
        Audit::record('withdrawal.paid', $a->id);
        Audit::record('router.reboot', $b->id);
        Audit::record('tenant.suspended', $b->id);

        $this->actingAs($this->makeAdmin(), 'admin');

        $this->get('/admin/audit?isp=' . $b->id)->assertOk()->assertSee('router.reboot')->assertDontSee('withdrawal.paid');
        $this->get('/admin/audit?action=withdrawal')->assertOk()->assertSee('withdrawal.paid')->assertDontSee('router.reboot');
        $this->get('/admin/audit?action=%25')->assertOk()->assertDontSee('withdrawal.paid');
    }

    // ── Support access ───────────────────────────────────────────────────────

    public function test_support_can_open_an_isp_dashboard_and_it_is_recorded_and_marked(): void
    {
        $tenant = $this->makeTenant('acme', ['name' => 'Acme WiFi']);
        $this->makeOwner($tenant);
        $this->makePackage($tenant, ['name' => 'Acme special']);
        $other = $this->makeTenant('other');
        $this->makePackage($other, ['name' => 'Other special']);
        $this->actingAs($this->makeAdmin(), 'admin');

        $this->post("/admin/tenants/{$tenant->id}/impersonate")->assertRedirect(route('dashboard.home'));

        $this->get('/dashboard/packages')
            ->assertOk()
            ->assertSee('Platform support is viewing this account')
            ->assertSee('Acme special')
            ->assertDontSee('Other special');

        $started = AuditLog::where('action', 'impersonation.started')->firstOrFail();
        $this->assertSame('admin', $started->actor_type);
        $this->assertSame('admin@example.test', $started->actor_label);
        $this->assertSame($tenant->id, $started->tenant_id);
    }

    public function test_money_actions_are_blocked_while_support_is_viewing_the_account(): void
    {
        $tenant = $this->makeTenant();
        $this->makeOwner($tenant);
        $agent = $this->makeAgent($tenant, 0);
        $this->makeWallet($tenant, 20000);
        $this->actingAs($this->makeAdmin(), 'admin');

        $this->post("/admin/tenants/{$tenant->id}/impersonate");

        $this->post('/dashboard/wallet/withdraw', ['amount' => 10000, 'mobile_number' => '0799000111'])->assertForbidden();
        $this->post('/dashboard/settings', ['brand_color' => '#0b7a75', 'default_language' => 'sw', 'withdrawal_number' => '0799000111'])->assertForbidden();
        $this->post("/dashboard/agents/{$agent->id}/topup", ['amount' => 5000])->assertForbidden();

        $this->assertSame(0, WithdrawalRequest::withoutGlobalScopes()->count());
        $this->assertSame(3, AuditLog::where('action', 'impersonation.blocked')->count());
    }

    public function test_leaving_the_support_view_logs_the_tenant_out_and_keeps_the_admin_in(): void
    {
        $tenant = $this->makeTenant();
        $this->makeOwner($tenant);
        $this->actingAs($this->makeAdmin(), 'admin');
        $this->post("/admin/tenants/{$tenant->id}/impersonate");

        $this->post('/impersonation/stop')->assertRedirect(route('admin.dashboard'));

        $this->assertGuest('tenant');
        $this->assertAuthenticated('admin');
        $this->assertSame(1, AuditLog::where('action', 'impersonation.ended')->count());
        $this->get('/dashboard')->assertRedirect();
    }

    public function test_an_isp_without_an_owner_cannot_be_opened_and_a_tenant_cannot_start_support_access(): void
    {
        $empty = $this->makeTenant('empty');
        $this->actingAs($this->makeAdmin(), 'admin');
        $this->post("/admin/tenants/{$empty->id}/impersonate")->assertSessionHasErrors('impersonate');

        $tenant = $this->makeTenant('acme');
        $owner  = $this->makeOwner($tenant);
        auth('admin')->logout();

        $this->actingAs($owner, 'tenant')->post("/admin/tenants/{$tenant->id}/impersonate")->assertRedirect(route('admin.login'));
        $this->assertSame(0, AuditLog::where('action', 'impersonation.started')->count());
    }
}
