<?php

namespace Tests\Feature;

use App\Jobs\GrantAccessJob;
use App\Models\PaymentWebhook;
use App\Models\TenantPackage;
use App\Models\TenantWallet;
use App\Models\Transaction;
use App\Models\WalletEntry;
use App\Models\WithdrawalRequest;
use App\Services\PaymentSettlement;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use LogicException;
use RuntimeException;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class FoundationsTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    // ── Ledger ───────────────────────────────────────────────────────────────

    public function test_the_same_ledger_movement_is_only_ever_applied_once(): void
    {
        $tenant = $this->makeTenant();
        $wallet = $this->makeWallet($tenant, 0);

        $this->assertTrue($wallet->credit(5000, WalletEntry::PAYMENT, 'TXN-1'));
        $this->assertFalse($wallet->credit(5000, WalletEntry::PAYMENT, 'TXN-1'), 'a retried callback must not pay twice');
        $this->assertTrue($wallet->credit(5000, WalletEntry::PAYMENT, 'TXN-2'));

        $this->assertSame(10000, $wallet->fresh()->balance);
        $this->assertSame(10000, $wallet->fresh()->total_earned);
        $this->assertSame(2, WalletEntry::count());
    }

    public function test_the_wallet_balance_always_equals_the_sum_of_the_ledger(): void
    {
        $tenant = $this->makeTenant();
        $wallet = $this->makeWallet($tenant, 0);

        $wallet->credit(20000, WalletEntry::PAYMENT, 'TXN-1');
        $wallet->debit(8000, WalletEntry::WITHDRAWAL, 'WDR-1');
        $wallet->refund(8000, 'WDR-1');
        $wallet->debit(3000, WalletEntry::WITHDRAWAL, 'WDR-2');

        $this->assertSame(17000, $wallet->fresh()->balance);
        $this->assertSame(17000, (int) WalletEntry::sum('amount'));
        $this->assertSame(20000, $wallet->fresh()->total_earned, 'refunds are not earnings');

        $last = WalletEntry::orderByDesc('id')->first();
        $this->assertSame(-3000, $last->amount);
        $this->assertSame(17000, $last->balance_after);
    }

    public function test_the_wallet_refuses_to_go_below_zero(): void
    {
        $tenant = $this->makeTenant();
        $wallet = $this->makeWallet($tenant, 1000);

        $this->assertFalse($wallet->debit(5000, WalletEntry::WITHDRAWAL, 'WDR-1'));

        $this->assertSame(1000, $wallet->fresh()->balance);
        $this->assertSame(1, WalletEntry::count(), 'a refused debit leaves no ledger entry');
    }

    public function test_ledger_entries_can_never_be_edited_or_deleted(): void
    {
        $tenant = $this->makeTenant();
        $this->makeWallet($tenant, 0)->credit(5000, WalletEntry::PAYMENT, 'TXN-1');
        $entry = WalletEntry::firstOrFail();

        try {
            $entry->update(['amount' => 999999]);
            $this->fail('editing an entry must be refused');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        try {
            $entry->delete();
            $this->fail('deleting an entry must be refused');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(5000, WalletEntry::firstOrFail()->amount);
    }

    public function test_settling_a_payment_writes_one_ledger_entry_tied_to_the_transaction(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['price' => 5000]);
        $this->makeRouter($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);

        $settlement = app(PaymentSettlement::class);
        $settlement->verifyAndSettle($payment);
        $settlement->verifyAndSettle($payment->fresh());

        $entry = WalletEntry::firstOrFail();
        $this->assertSame(WalletEntry::PAYMENT, $entry->type);
        $this->assertSame('TXN-' . $payment->id, $entry->reference);
        $this->assertSame(5000, $entry->amount);
        $this->assertSame('ORD-1', $entry->meta['order_id']);
        $this->assertSame(1, WalletEntry::count());
    }

    public function test_a_withdrawal_and_its_rejection_leave_a_traceable_trail(): void
    {
        $tenant = $this->makeTenant();
        $owner  = $this->makeOwner($tenant);
        $this->makeWallet($tenant, 10000);

        $this->actingAs($owner, 'tenant')
            ->post('/dashboard/wallet/withdraw', ['amount' => 6000, 'mobile_number' => '0712345678']);

        $withdrawal = WithdrawalRequest::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(4000, TenantWallet::withoutGlobalScopes()->value('balance'));

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post("/admin/withdrawals/{$withdrawal->id}/reject", ['reason' => 'test']);

        $this->assertSame(
            [WalletEntry::OPENING_BALANCE, WalletEntry::WITHDRAWAL, WalletEntry::WITHDRAWAL_REFUND],
            WalletEntry::withoutGlobalScopes()->orderBy('id')->pluck('type')->all()
        );
        $this->assertSame(
            ['WDR-' . $withdrawal->id, 'WDR-' . $withdrawal->id],
            WalletEntry::withoutGlobalScopes()->whereIn('type', ['withdrawal', 'withdrawal_refund'])->pluck('reference')->all()
        );

        $this->assertSame(10000, TenantWallet::withoutGlobalScopes()->value('balance'));
        $this->assertSame(10000, (int) WalletEntry::withoutGlobalScopes()->sum('amount'));
    }

    public function test_the_audit_catches_a_balance_that_the_ledger_does_not_explain(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['price' => 10000]);
        $this->makePendingPayment($tenant, $package, 'ORD-1', ['status' => 'completed']);
        $wallet = $this->makeWallet($tenant, 8000);

        $this->artisan('wallet:audit')->assertSuccessful();

        // Someone edits the balance by hand: still under the payment ceiling, but the ledger disagrees.
        TenantWallet::withoutGlobalScopes()->whereKey($wallet->id)->update(['balance' => 9000]);

        $this->artisan('wallet:audit')->assertFailed();
    }

    // ── Tenant isolation ─────────────────────────────────────────────────────

    public function test_models_only_see_the_current_tenants_rows(): void
    {
        $a = $this->makeTenant('alpha');
        $b = $this->makeTenant('bravo');
        $this->makePackage($a, ['name' => 'A1']);
        $this->makePackage($a, ['name' => 'A2']);
        $this->makePackage($b, ['name' => 'B1']);

        $this->assertSame(3, TenantPackage::count(), 'console and admin code sees everything');
        $this->assertSame(2, CurrentTenant::runAs($a->id, fn () => TenantPackage::count()));
        $this->assertSame(['B1'], CurrentTenant::runAs($b->id, fn () => TenantPackage::pluck('name')->all()));
        $this->assertSame(3, CurrentTenant::runAs($a->id, fn () => TenantPackage::withoutGlobalScopes()->count()));
    }

    public function test_new_rows_are_stamped_with_the_current_tenant(): void
    {
        $a = $this->makeTenant('alpha');

        $package = CurrentTenant::runAs($a->id, fn () => TenantPackage::create([
            'name' => 'Stamped', 'price' => 1000, 'duration_hours' => 1,
            'speed_down_mbps' => 1, 'speed_up_mbps' => 1, 'mikrotik_profile' => 'p',
        ]));

        $this->assertSame($a->id, $package->tenant_id);
    }

    public function test_an_owner_cannot_open_another_tenants_package_even_by_guessing_its_id(): void
    {
        $mine     = $this->makeTenant('alpha');
        $theirs   = $this->makeTenant('bravo');
        $foreign  = $this->makePackage($theirs, ['name' => 'Secret plan']);
        $owner    = $this->makeOwner($mine);

        $response = $this->actingAs($owner, 'tenant')->get("/dashboard/packages/{$foreign->id}/edit");

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $response->assertDontSee('Secret plan');
    }

    public function test_an_owners_dashboard_lists_only_their_own_data_even_with_an_unfiltered_query(): void
    {
        $mine   = $this->makeTenant('alpha');
        $theirs = $this->makeTenant('bravo');
        $this->makePackage($mine, ['name' => 'Mine only']);
        $this->makePackage($theirs, ['name' => 'Theirs only']);

        $this->actingAs($this->makeOwner($mine), 'tenant')
            ->get('/dashboard/packages')
            ->assertOk()
            ->assertSee('Mine only')
            ->assertDontSee('Theirs only');
    }

    public function test_the_admin_panel_still_sees_every_tenant_even_with_a_tenant_session_open(): void
    {
        $mine   = $this->makeTenant('alpha');
        $theirs = $this->makeTenant('bravo');
        foreach ([$mine, $theirs] as $tenant) {
            WithdrawalRequest::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'amount' => 6000, 'fee_amount' => 300, 'net_amount' => 5700,
                'mobile_number' => '0712345678', 'status' => 'pending',
            ]);
        }

        $this->actingAs($this->makeOwner($mine), 'tenant')
            ->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/withdrawals')
            ->assertOk()
            ->assertSee('Alpha WiFi')
            ->assertSee('Bravo WiFi');
    }

    public function test_the_tenant_context_never_outlives_the_request(): void
    {
        $tenant = $this->makeTenant('alpha');
        $this->makePackage($tenant);

        $this->getJson('/api/payment/status?tenant=alpha&transaction_id=x')->assertOk();

        $this->assertNull(CurrentTenant::id());
    }

    // ── Webhook inbox ────────────────────────────────────────────────────────

    public function test_every_callback_is_stored_as_received_with_what_we_did_about_it(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);

        $this->postJson('/api/payment/callback', ['order_id' => 'ORD-1', 'payment_status' => 'COMPLETED', 'extra' => 'kept'])->assertOk();
        $this->postJson('/api/payment/callback', ['order_id' => 'ORD-1'])->assertOk();
        $this->postJson('/api/payment/callback', ['order_id' => 'NOPE'])->assertNotFound();

        $hooks = PaymentWebhook::orderBy('id')->get();
        $this->assertCount(3, $hooks);
        $this->assertSame(['settled', 'already_processed', 'not_found'], $hooks->pluck('result')->all());
        $this->assertSame('kept', $hooks[0]->payload['extra']);
        $this->assertSame('ORD-1', $hooks[0]->order_id);
        $this->assertNotNull($hooks[0]->processed_at);
    }

    // ── Access grant queue ───────────────────────────────────────────────────

    public function test_a_settled_payment_queues_the_access_grant_instead_of_running_it_inline(): void
    {
        Queue::fake();
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->makeRouter($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);

        app(PaymentSettlement::class)->verifyAndSettle($payment);

        Queue::assertPushed(GrantAccessJob::class, fn ($job) => $job->transactionId === $payment->id);
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->provision_status);
    }

    public function test_the_access_job_retries_until_the_router_accepts_and_then_marks_done(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->makeRouter($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1', ['status' => 'completed', 'voucher_code' => 'TNABC123']);

        $job = new GrantAccessJob($payment->id);

        $this->fakeExternalServices([], routerOk: false);
        try {
            $job->handle(app(\App\Services\AccessGranter::class));
            $this->fail('a router that refuses must make the job fail so the queue retries it');
        } catch (RuntimeException) {
            $this->assertSame('pending', $payment->fresh()->provision_status);
        }

        $this->fakeExternalServices([], routerOk: true);
        $job->handle(app(\App\Services\AccessGranter::class));

        $this->assertSame('done', $payment->fresh()->provision_status);

        // Running it again after success does nothing.
        $before = $this->hotspotUsersCreated();
        $job->handle(app(\App\Services\AccessGranter::class));
        $this->assertSame($before, $this->hotspotUsersCreated());
    }

    public function test_when_every_retry_is_used_up_the_payment_stays_safe_and_the_problem_is_visible(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['price' => 5000]);
        $this->makeRouter($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED'], routerOk: false);

        app(PaymentSettlement::class)->verifyAndSettle($payment);

        $payment->refresh();
        $this->assertSame('completed', $payment->status, 'the customer paid, a router problem never undoes that');
        $this->assertSame('failed', $payment->provision_status);
        $this->assertNotNull($payment->provision_error);
        $this->assertSame(5000, TenantWallet::withoutGlobalScopes()->value('balance'));
    }

    // ── Public pages ─────────────────────────────────────────────────────────

    public function test_the_router_setup_guide_is_public_and_needs_no_isp_signed_in(): void
    {
        // Support sends people here when a setup goes wrong, so it has to open for anyone, from
        // a phone, without a login.
        $this->get('/guide/router-setup')
            ->assertOk()
            ->assertSee('Set up your router')
            ->assertSee('Paste it again');
    }
}
