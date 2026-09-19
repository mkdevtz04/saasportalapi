<?php

namespace Tests\Feature;

use App\Models\AgentWallet;
use App\Models\PlatformBillingLog;
use App\Models\TenantWallet;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * The tenant wallet is withdrawable money. It may only ever be filled by payments
 * the gateway confirmed. Voucher and agent sales are cash the tenant already holds.
 */
class WalletRulesTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function makeVoucher($tenant, $package, string $code = 'TNTESTCODE1'): Voucher
    {
        return Voucher::create([
            'tenant_id'  => $tenant->id,
            'package_id' => $package->id,
            'code'       => $code,
            'batch_ref'  => 'BTCHTEST',
        ]);
    }

    // ── Vouchers ─────────────────────────────────────────────────────────────

    public function test_redeeming_a_voucher_never_credits_the_tenant_wallet(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['price' => 5000]);
        $this->makeRouter($tenant);
        $voucher = $this->makeVoucher($tenant, $package);
        $this->fakeExternalServices();

        $this->postJson('/api/voucher/redeem?tenant=acme', ['code' => 'tntestcode1'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNotNull($voucher->fresh()->used_at);
        $this->assertSame(0, TenantWallet::count());
        $this->assertSame(0, PlatformBillingLog::count());
        $this->assertSame(1, $this->hotspotUsersCreated());

        // Recorded as a sale for reports, but tagged so it is never mistaken for gateway money.
        $sale = Transaction::firstOrFail();
        $this->assertSame(Transaction::CHANNEL_VOUCHER, $sale->channel);
        $this->assertSame('completed', $sale->status);
        $this->assertSame(5000, $sale->amount);
    }

    public function test_a_voucher_is_given_back_when_the_router_cannot_be_reached(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->makeRouter($tenant);
        $voucher = $this->makeVoucher($tenant, $package);
        $this->fakeExternalServices([], routerOk: false);

        $this->postJson('/api/voucher/redeem?tenant=acme', ['code' => 'TNTESTCODE1'])
            ->assertStatus(503)
            ->assertJson(['ok' => false]);

        $this->assertNull($voucher->fresh()->used_at, 'the customer got no service, so the voucher stays usable');
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, TenantWallet::count());

        // The customer can simply try again once the router is back.
        $this->fakeExternalServices();
        $this->postJson('/api/voucher/redeem?tenant=acme', ['code' => 'TNTESTCODE1'])->assertOk();
        $this->assertNotNull($voucher->fresh()->used_at);
    }

    public function test_a_voucher_cannot_be_redeemed_twice(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->makeRouter($tenant);
        $this->makeVoucher($tenant, $package);
        $this->fakeExternalServices();

        $this->postJson('/api/voucher/redeem?tenant=acme', ['code' => 'TNTESTCODE1'])->assertOk();
        $this->postJson('/api/voucher/redeem?tenant=acme', ['code' => 'TNTESTCODE1'])->assertStatus(422);

        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, $this->hotspotUsersCreated());
    }

    public function test_a_voucher_from_another_tenant_is_rejected(): void
    {
        $other = $this->makeTenant('other');
        $this->makeVoucher($other, $this->makePackage($other));
        $this->makeTenant('acme');
        $this->fakeExternalServices();

        $this->postJson('/api/voucher/redeem?tenant=acme', ['code' => 'TNTESTCODE1'])->assertStatus(422);
    }

    // ── Agent sales ──────────────────────────────────────────────────────────

    public function test_an_agent_sale_spends_the_agent_balance_and_never_credits_the_tenant_wallet(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['price' => 5000]);
        $voucher = $this->makeVoucher($tenant, $package);
        $agent   = $this->makeAgent($tenant, walletBalance: 20000);

        $this->actingAs($agent, 'tenant')
            ->postJson('/pos/sell', ['package_id' => $package->id])
            ->assertOk()
            ->assertJson(['ok' => true, 'code' => 'TNTESTCODE1', 'wallet_balance' => 15000]);

        $this->assertSame(15000, AgentWallet::where('tenant_user_id', $agent->id)->value('balance'));
        $this->assertNotNull($voucher->fresh()->sold_at);
        $this->assertSame(0, TenantWallet::count(), 'agent cash is not platform money');
    }

    public function test_an_agent_cannot_sell_beyond_their_balance(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['price' => 5000]);
        $this->makeVoucher($tenant, $package);
        $agent = $this->makeAgent($tenant, walletBalance: 1000);

        $this->actingAs($agent, 'tenant')
            ->postJson('/pos/sell', ['package_id' => $package->id])
            ->assertStatus(422);
    }

    // ── Withdrawals ──────────────────────────────────────────────────────────

    public function test_a_withdrawal_takes_the_fee_from_the_payout_and_the_gross_from_the_wallet(): void
    {
        $tenant = $this->makeTenant();
        $owner  = $this->makeOwner($tenant);
        $this->makeWallet($tenant, 20000);

        $this->actingAs($owner, 'tenant')
            ->post('/dashboard/wallet/withdraw', ['amount' => 10000, 'mobile_number' => '0712345678'])
            ->assertSessionHasNoErrors();

        $this->assertSame(10000, TenantWallet::where('tenant_id', $tenant->id)->value('balance'));

        $withdrawal = WithdrawalRequest::firstOrFail();
        $this->assertSame(10000, $withdrawal->amount);
        $this->assertSame(500, $withdrawal->fee_amount);
        $this->assertSame(9500, $withdrawal->net_amount);
        $this->assertSame('pending', $withdrawal->status);
    }

    public function test_a_tenant_cannot_withdraw_more_than_the_wallet_holds_or_below_the_minimum(): void
    {
        $tenant = $this->makeTenant();
        $owner  = $this->makeOwner($tenant);
        $this->makeWallet($tenant, 10000);

        $this->actingAs($owner, 'tenant')
            ->post('/dashboard/wallet/withdraw', ['amount' => 15000, 'mobile_number' => '0712345678'])
            ->assertSessionHasErrors('amount');
        $this->actingAs($owner, 'tenant')
            ->post('/dashboard/wallet/withdraw', ['amount' => 1000, 'mobile_number' => '0712345678'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, WithdrawalRequest::count());
        $this->assertSame(10000, TenantWallet::where('tenant_id', $tenant->id)->value('balance'));
    }

    public function test_the_same_money_cannot_be_withdrawn_twice(): void
    {
        $tenant = $this->makeTenant();
        $owner  = $this->makeOwner($tenant);
        $this->makeWallet($tenant, 10000);

        $this->actingAs($owner, 'tenant');
        $this->post('/dashboard/wallet/withdraw', ['amount' => 10000, 'mobile_number' => '0712345678']);
        $this->post('/dashboard/wallet/withdraw', ['amount' => 10000, 'mobile_number' => '0712345678'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, WithdrawalRequest::count());
        $this->assertSame(0, TenantWallet::where('tenant_id', $tenant->id)->value('balance'));
    }

    public function test_the_platform_earns_its_fee_once_when_a_withdrawal_is_marked_paid(): void
    {
        $tenant     = $this->makeTenant();
        $withdrawal = WithdrawalRequest::create([
            'tenant_id' => $tenant->id, 'amount' => 10000, 'fee_amount' => 500, 'net_amount' => 9500,
            'mobile_number' => '0712345678', 'status' => 'pending',
        ]);
        $this->actingAs($this->makeAdmin(), 'admin');

        $this->post("/admin/withdrawals/{$withdrawal->id}/approve")->assertSessionHas('success');
        $this->assertSame(0, PlatformBillingLog::count(), 'no revenue until money is actually paid out');

        $this->post("/admin/withdrawals/{$withdrawal->id}/paid")->assertSessionHas('success');
        $this->post("/admin/withdrawals/{$withdrawal->id}/paid")->assertStatus(422);

        $this->assertSame('paid', $withdrawal->fresh()->status);
        $this->assertSame(1, PlatformBillingLog::count());
        $this->assertSame(500, (int) PlatformBillingLog::value('amount'));
        $this->assertSame('WDR-' . $withdrawal->id, PlatformBillingLog::value('reference'));
    }

    public function test_rejecting_a_withdrawal_refunds_the_gross_amount_exactly_once(): void
    {
        $tenant = $this->makeTenant();
        $this->makeWallet($tenant, 0, totalEarned: 10000);
        $withdrawal = WithdrawalRequest::create([
            'tenant_id' => $tenant->id, 'amount' => 10000, 'fee_amount' => 500, 'net_amount' => 9500,
            'mobile_number' => '0712345678', 'status' => 'pending',
        ]);
        $this->actingAs($this->makeAdmin(), 'admin');

        $this->post("/admin/withdrawals/{$withdrawal->id}/reject", ['reason' => 'wrong number'])
            ->assertSessionHas('success');
        $this->post("/admin/withdrawals/{$withdrawal->id}/reject", ['reason' => 'again'])
            ->assertStatus(422);

        $this->assertSame(10000, TenantWallet::where('tenant_id', $tenant->id)->value('balance'));
        $this->assertSame(0, PlatformBillingLog::count(), 'a rejected withdrawal carries no fee');
        $this->assertSame('rejected', $withdrawal->fresh()->status);
    }

    public function test_the_withdrawal_fee_is_rounded_to_whole_shillings_and_follows_the_config(): void
    {
        $this->assertSame(250, WithdrawalRequest::feeFor(5001));

        config(['platform.withdrawal_fee_pct' => 2.5]);
        $this->assertSame(250, WithdrawalRequest::feeFor(10000));

        config(['platform.withdrawal_fee_pct' => 0]);
        $this->assertSame(0, WithdrawalRequest::feeFor(10000));
    }

    // ── Audit ────────────────────────────────────────────────────────────────

    public function test_the_audit_flags_wallets_that_portal_payments_cannot_explain(): void
    {
        $honest = $this->makeTenant('honest');
        $this->makeWallet($honest, 9500, totalEarned: 10000);
        $package = $this->makePackage($honest, ['price' => 10000]);
        $this->makePendingPayment($honest, $package, 'ORD-1', ['status' => 'completed']);
        WithdrawalRequest::create([
            'tenant_id' => $honest->id, 'amount' => 500, 'fee_amount' => 25, 'net_amount' => 475,
            'mobile_number' => '0712345678', 'status' => 'paid',
        ]);

        $this->artisan('wallet:audit')->assertSuccessful();

        // Money that arrived through vouchers or agent sales, never through the gateway.
        $inflated = $this->makeTenant('inflated');
        $this->makeWallet($inflated, 50000);

        $this->artisan('wallet:audit')->assertFailed();
    }

    public function test_the_audit_ignores_voucher_sales_when_working_out_what_a_tenant_earned(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['price' => 10000]);
        $this->makeWallet($tenant, 10000);
        $this->makePendingPayment($tenant, $package, null, [
            'status'  => 'completed',
            'channel' => Transaction::CHANNEL_VOUCHER,
        ]);

        $this->artisan('wallet:audit')->assertFailed();
    }
}
