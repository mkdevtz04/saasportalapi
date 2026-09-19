<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function failLogin(string $url, string $email, string $ip = '198.51.100.7'): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post($url, ['email' => $email, 'password' => 'wrong-password']);
    }

    // ── Sign-in throttling ───────────────────────────────────────────────────

    public function test_repeated_wrong_passwords_lock_the_sign_in_even_for_the_right_password(): void
    {
        $owner = $this->makeOwner($this->makeTenant());

        for ($i = 0; $i < 5; $i++) {
            $this->failLogin('/login', $owner->email);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->post('/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('tenant');
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('email'));
    }

    public function test_a_correct_password_before_the_limit_signs_in_and_resets_the_count(): void
    {
        $owner = $this->makeOwner($this->makeTenant());

        for ($i = 0; $i < 4; $i++) {
            $this->failLogin('/login', $owner->email);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->post('/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticated('tenant');

        auth('tenant')->logout();

        // The count started again, so four more mistakes are still allowed.
        for ($i = 0; $i < 4; $i++) {
            $this->failLogin('/login', $owner->email);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->post('/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertSessionHasNoErrors();
    }

    public function test_one_persons_mistakes_do_not_lock_out_someone_else(): void
    {
        $tenant = $this->makeTenant();
        $alice  = $this->makeOwner($tenant, 'alice@example.test');
        $bob    = $this->makeOwner($tenant, 'bob@example.test');

        for ($i = 0; $i < 6; $i++) {
            $this->failLogin('/login', $alice->email);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->post('/login', ['email' => $bob->email, 'password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticated('tenant');
    }

    public function test_guessing_one_account_from_many_addresses_is_stopped_too(): void
    {
        $owner = $this->makeOwner($this->makeTenant());

        for ($i = 1; $i <= 15; $i++) {
            $this->failLogin('/login', $owner->email, '203.0.113.' . $i);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.200'])
            ->post('/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('tenant');
    }

    public function test_the_platform_admin_sign_in_is_throttled_and_every_attempt_is_recorded(): void
    {
        $admin = $this->makeAdmin();

        for ($i = 0; $i < 5; $i++) {
            $this->failLogin('/admin/login', $admin->email);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('admin');

        $this->assertSame(5, AuditLog::where('action', 'auth.admin_login_failed')->count());
        $this->assertSame(1, AuditLog::where('action', 'auth.locked_out')->count());
    }

    public function test_a_successful_admin_sign_in_is_recorded(): void
    {
        $admin = $this->makeAdmin();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));

        $this->assertSame(1, AuditLog::where('action', 'auth.admin_login')->count());
    }

    public function test_sign_up_is_limited_per_address(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/register', [])->assertStatus(302);
        }

        $this->post('/register', [])->assertStatus(429);
    }

    public function test_voucher_guessing_is_slowed_down(): void
    {
        $this->makeTenant();
        $this->fakeExternalServices();

        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/voucher/redeem?tenant=acme', ['code' => 'TNGUESS' . $i . 'X'])->assertStatus(422);
        }

        $this->postJson('/api/voucher/redeem?tenant=acme', ['code' => 'TNGUESS99X'])->assertStatus(429);
    }

    // ── Headers and proxies ──────────────────────────────────────────────────

    public function test_every_response_carries_the_browser_protection_headers(): void
    {
        $this->makeTenant();

        foreach (['/', '/login', '/portal?tenant=acme', '/up'] as $path) {
            $response = $this->get($path);

            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'DENY');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $this->assertNotNull($response->headers->get('Permissions-Policy'), $path);
        }
    }

    public function test_the_https_only_header_appears_only_on_secure_requests(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->withServerVariables(['HTTPS' => 'on'])
            ->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_the_visitor_address_is_read_from_the_proxy_only_when_a_proxy_is_trusted(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->fakeExternalServices();
        $forward = ['X-Forwarded-For' => '198.51.100.44'];

        config(['trustedproxy.proxies' => null]);
        $this->postJson('/api/payment/initiate?tenant=acme', ['phone' => '0711111111', 'package_id' => $package->id], $forward)->assertOk();
        $this->assertNotSame('198.51.100.44', Transaction::orderBy('id')->first()->customer_ip, 'a forged header must not be believed without a trusted proxy');

        config(['trustedproxy.proxies' => '*']);
        $this->postJson('/api/payment/initiate?tenant=acme', ['phone' => '0722222222', 'package_id' => $package->id], $forward)->assertOk();
        $this->assertSame('198.51.100.44', Transaction::orderByDesc('id')->first()->customer_ip);
    }

    // ── Health ───────────────────────────────────────────────────────────────

    public function test_health_is_down_when_the_scheduler_has_never_run(): void
    {
        $this->getJson('/health')->assertStatus(503)->assertExactJson(['status' => 'down']);
    }

    public function test_health_is_ok_when_the_scheduler_is_alive_and_shows_nothing_extra_without_the_token(): void
    {
        Cache::forever('scheduler:last_run', now()->timestamp);
        config(['app.health_token' => 'secret-health-token']);

        $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);
        $this->getJson('/health', ['X-Health-Token' => 'wrong'])->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_health_shows_the_details_to_a_caller_with_the_token(): void
    {
        Cache::forever('scheduler:last_run', now()->timestamp);
        config(['app.health_token' => 'secret-health-token']);

        $this->getJson('/health', ['X-Health-Token' => 'secret-health-token'])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.ok', true)
            ->assertJsonPath('checks.scheduler.ok', true)
            ->assertJsonPath('checks.queue.waiting', 0)
            ->assertJsonPath('checks.payments.stuck_pending', 0);
    }

    public function test_health_is_never_detailed_when_no_token_is_configured(): void
    {
        Cache::forever('scheduler:last_run', now()->timestamp);
        config(['app.health_token' => '']);

        $this->getJson('/health', ['X-Health-Token' => ''])->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_health_says_down_when_the_scheduler_stopped_a_while_ago(): void
    {
        Cache::forever('scheduler:last_run', now()->subMinutes(20)->timestamp);

        $this->getJson('/health')->assertStatus(503);
    }

    public function test_health_says_degraded_for_a_payment_stuck_pending_or_a_customer_without_access(): void
    {
        Cache::forever('scheduler:last_run', now()->timestamp);
        config(['app.health_token' => 'secret-health-token']);
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);

        $stuck = $this->makePendingPayment($tenant, $package, 'ORD-1');
        Transaction::whereKey($stuck->id)->update(['created_at' => now()->subMinutes(30)]);

        $this->getJson('/health', ['X-Health-Token' => 'secret-health-token'])
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.payments.stuck_pending', 1);
    }

    // ── Administrator accounts ───────────────────────────────────────────────

    public function test_an_administrator_is_created_with_a_generated_strong_password_shown_once(): void
    {
        $this->artisan('admin:create', ['email' => 'Boss@Example.test'])
            ->expectsOutputToContain('Administrator created: boss@example.test')
            ->expectsOutputToContain('Password (shown once')
            ->assertSuccessful();

        $admin = \App\Models\PlatformAdmin::firstOrFail();
        $this->assertSame('boss@example.test', $admin->email);
        $this->assertNotSame('', $admin->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::needsRehash($admin->password) || str_starts_with($admin->password, '$2y$') || str_starts_with($admin->password, '$argon'), 'stored hashed');
    }

    public function test_weak_passwords_bad_emails_and_duplicates_are_refused(): void
    {
        $this->artisan('admin:create', ['email' => 'not-an-email'])->assertFailed();
        $this->artisan('admin:create', ['email' => 'boss@example.test', '--password' => 'short'])->assertFailed();
        $this->assertSame(0, \App\Models\PlatformAdmin::count());

        $this->artisan('admin:create', ['email' => 'boss@example.test', '--password' => 'a-long-enough-password'])->assertSuccessful();
        $this->artisan('admin:create', ['email' => 'BOSS@example.test', '--password' => 'another-long-password'])->assertFailed();
        $this->assertSame(1, \App\Models\PlatformAdmin::count());
    }

    public function test_an_administrator_created_by_the_command_can_sign_in(): void
    {
        $this->artisan('admin:create', ['email' => 'boss@example.test', '--password' => 'a-long-enough-password'])->assertSuccessful();

        $this->post('/admin/login', ['email' => 'boss@example.test', 'password' => 'a-long-enough-password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    private function clearAdminEnv(): void
    {
        putenv('ADMIN_EMAIL');
        putenv('ADMIN_PASSWORD');
        unset($_ENV['ADMIN_EMAIL'], $_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_EMAIL'], $_SERVER['ADMIN_PASSWORD']);
    }

    private function setAdminEnv(string $email, string $password): void
    {
        putenv("ADMIN_EMAIL={$email}");
        putenv("ADMIN_PASSWORD={$password}");
        $_ENV['ADMIN_EMAIL'] = $_SERVER['ADMIN_EMAIL'] = $email;
        $_ENV['ADMIN_PASSWORD'] = $_SERVER['ADMIN_PASSWORD'] = $password;
    }

    public function test_on_a_live_server_the_seeder_never_creates_an_account_with_a_default_or_weak_password(): void
    {
        $this->app['env'] = 'production';
        $this->clearAdminEnv();

        $this->artisan('db:seed', ['--class' => \Database\Seeders\PlatformAdminSeeder::class, '--force' => true])->assertSuccessful();
        $this->assertSame(0, \App\Models\PlatformAdmin::count(), 'no default account on a live server');

        $this->setAdminEnv('boss@example.test', 'changeme123');   // the old default: only 11 characters
        $this->artisan('db:seed', ['--class' => \Database\Seeders\PlatformAdminSeeder::class, '--force' => true])->assertSuccessful();
        $this->assertSame(0, \App\Models\PlatformAdmin::count(), 'a weak password is refused on a live server');

        $this->setAdminEnv('boss@example.test', 'a-long-enough-password');
        $this->artisan('db:seed', ['--class' => \Database\Seeders\PlatformAdminSeeder::class, '--force' => true])->assertSuccessful();
        $this->assertSame(1, \App\Models\PlatformAdmin::count());

        $this->clearAdminEnv();
    }

    public function test_on_a_local_machine_the_seeder_creates_the_familiar_admin_and_you_can_sign_in(): void
    {
        $this->app['env'] = 'local';
        $this->clearAdminEnv();

        $this->artisan('db:seed', ['--class' => \Database\Seeders\PlatformAdminSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertSame(['admin@trinetpay.online'], \App\Models\PlatformAdmin::pluck('email')->all());

        $this->app['env'] = 'testing';

        $this->post('/admin/login', ['email' => 'admin@trinetpay.online', 'password' => 'changeme123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');

        // Running it again changes nothing and does not duplicate the account.
        $this->artisan('db:seed', ['--class' => \Database\Seeders\PlatformAdminSeeder::class, '--force' => true])->assertSuccessful();
        $this->assertSame(1, \App\Models\PlatformAdmin::count());
    }

    // ── Housekeeping ─────────────────────────────────────────────────────────

    public function test_old_bulk_data_is_removed_and_money_records_never_are(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $router  = $this->makeRouter($tenant);

        DB::table('radpostauth')->insert([
            ['username' => 'old', 'pass' => '', 'reply' => 'Access-Reject', 'authdate' => now()->subDays(20)],
            ['username' => 'new', 'pass' => '', 'reply' => 'Access-Accept', 'authdate' => now()->subDay()],
        ]);
        DB::table('router_commands')->insert([
            ['tenant_id' => $tenant->id, 'router_id' => $router->id, 'type' => 'reboot', 'status' => 'delivered', 'delivered_at' => now()->subDays(40), 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenant->id, 'router_id' => $router->id, 'type' => 'reboot', 'status' => 'pending', 'delivered_at' => null, 'created_at' => now()->subDays(40), 'updated_at' => now()],
        ]);
        DB::table('payment_webhooks')->insert([
            ['payload' => '{}', 'created_at' => now()->subDays(200)],
            ['payload' => '{}', 'created_at' => now()->subDays(2)],
        ]);
        $oldPayment = $this->makePendingPayment($tenant, $package, 'ORD-OLD', ['status' => 'completed']);
        Transaction::whereKey($oldPayment->id)->update(['created_at' => now()->subYears(3)]);
        \App\Support\Audit::record('old.action');
        DB::table('audit_logs')->update(['created_at' => now()->subYears(3)]);

        $this->artisan('data:prune')->assertSuccessful();

        $this->assertSame(['new'], DB::table('radpostauth')->pluck('username')->all());
        $this->assertSame(1, DB::table('router_commands')->count(), 'a delivered command is removed, a waiting one never is');
        $this->assertSame('pending', DB::table('router_commands')->value('status'));
        $this->assertSame(1, DB::table('payment_webhooks')->count());
        $this->assertSame(1, Transaction::withoutGlobalScopes()->count(), 'payments are kept for ever');
        $this->assertSame(1, AuditLog::count(), 'the audit trail is kept for ever');
    }

    public function test_every_recurring_job_is_on_the_schedule(): void
    {
        Artisan::call('schedule:list');
        $list = Artisan::output();

        foreach (['payments:reconcile', 'router:heartbeat', 'radius:prune', 'data:prune', 'access:expire', 'scheduler-beat'] as $job) {
            $this->assertStringContainsString($job, $list, "{$job} is not scheduled");
        }
    }
}
