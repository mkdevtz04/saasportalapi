<?php

namespace Tests\Feature;

use App\Models\PlatformBillingLog;
use App\Models\TenantWallet;
use App\Models\Transaction;
use App\Services\PaymentSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    // ── Settlement ───────────────────────────────────────────────────────────

    public function test_a_confirmed_payment_settles_once_and_credits_the_full_amount(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['price' => 5000]);
        $this->makeRouter($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);

        $settlement = app(PaymentSettlement::class);
        $settlement->verifyAndSettle($payment);
        $settlement->verifyAndSettle($payment->fresh());

        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertStringStartsWith('TN', $payment->voucher_code);
        $this->assertNotNull($payment->expires_at);

        // No fee at payment time: the fee is only taken when the tenant withdraws.
        $wallet = TenantWallet::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(5000, $wallet->balance);
        $this->assertSame(5000, $wallet->total_earned);
        $this->assertSame(0, PlatformBillingLog::count());

        $this->assertSame(1, $this->hotspotUsersCreated());
    }

    public function test_two_stale_copies_of_one_payment_cannot_double_credit(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['price' => 5000]);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices();

        // A callback and a status poll both loaded the row while it was still pending.
        $copyA = Transaction::find($payment->id);
        $copyB = Transaction::find($payment->id);

        $settlement = app(PaymentSettlement::class);
        $this->assertTrue($settlement->settle($copyA));
        $this->assertFalse($settlement->settle($copyB));

        $this->assertSame(5000, TenantWallet::where('tenant_id', $tenant->id)->value('balance'));
    }

    public function test_a_payment_the_gateway_reports_late_is_still_settled_after_being_marked_failed(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');

        $this->fakeExternalServices(['ORD-1' => 'FAILED']);
        $settlement = app(PaymentSettlement::class);
        $settlement->verifyAndSettle($payment);
        $this->assertSame('failed', $payment->fresh()->status);

        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);
        $settlement->verifyAndSettle($payment->fresh());

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(5000, TenantWallet::where('tenant_id', $tenant->id)->value('balance'));
    }

    public function test_an_unknown_gateway_status_never_fails_a_payment(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices(['ORD-1' => 'PROCESSING']);

        app(PaymentSettlement::class)->verifyAndSettle($payment);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    // ── Callback ─────────────────────────────────────────────────────────────

    public function test_the_callback_does_not_trust_the_status_in_its_payload(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices(['ORD-1' => 'PENDING']);

        $this->postJson('/api/payment/callback', ['order_id' => 'ORD-1', 'payment_status' => 'COMPLETED'])
            ->assertOk();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(0, TenantWallet::count());
    }

    public function test_the_callback_settles_when_the_gateway_confirms(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);

        $this->postJson('/api/payment/callback', ['order_id' => 'ORD-1'])->assertOk();

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(5000, TenantWallet::where('tenant_id', $tenant->id)->value('balance'));
    }

    public function test_a_callback_for_an_unknown_order_is_rejected(): void
    {
        $this->fakeExternalServices();

        $this->postJson('/api/payment/callback', ['order_id' => 'NOPE', 'payment_status' => 'COMPLETED'])
            ->assertNotFound();
        $this->postJson('/api/payment/callback', [])->assertNotFound();

        $this->assertSame(0, TenantWallet::count());
    }

    // ── Status polling ───────────────────────────────────────────────────────

    public function test_polling_uses_the_stored_order_id_and_ignores_the_one_the_browser_sends(): void
    {
        $tenant = $this->makeTenant();
        $cheap  = $this->makePackage($tenant, ['name' => 'Cheap', 'price' => 500]);
        $dear   = $this->makePackage($tenant, ['name' => 'Dear', 'price' => 30000]);

        // The attacker really paid for the cheap order and never paid for the expensive one.
        $this->makePendingPayment($tenant, $cheap, 'ORD-CHEAP');
        $expensive = $this->makePendingPayment($tenant, $dear, 'ORD-DEAR');
        $this->fakeExternalServices(['ORD-CHEAP' => 'COMPLETED', 'ORD-DEAR' => 'PENDING']);

        $this->getJson('/api/payment/status?tenant=acme&transaction_id=' . $expensive->public_id . '&order_id=ORD-CHEAP')
            ->assertOk()
            ->assertJson(['status' => 'pending'])
            ->assertJsonMissing(['status' => 'paid']);

        $this->assertSame('pending', $expensive->fresh()->status);
        $this->assertNull($expensive->fresh()->voucher_code);
        $this->assertSame(0, TenantWallet::count());
    }

    public function test_polling_hands_out_the_wifi_token_only_after_the_gateway_confirms(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->makeRouter($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');

        $this->fakeExternalServices(['ORD-1' => 'PENDING']);
        $this->getJson('/api/payment/status?tenant=acme&transaction_id=' . $payment->public_id)
            ->assertJson(['status' => 'pending'])
            ->assertJsonMissingPath('wifi_token');

        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);
        $response = $this->getJson('/api/payment/status?tenant=acme&transaction_id=' . $payment->public_id)
            ->assertJson(['status' => 'paid', 'package' => 'Daily']);

        $this->assertSame($payment->fresh()->voucher_code, $response->json('wifi_token'));
    }

    public function test_polling_cannot_find_transactions_by_their_sequential_id(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1', [
            'status'       => 'completed',
            'voucher_code' => 'TNSECRET123',
        ]);
        $this->fakeExternalServices();

        $this->getJson('/api/payment/status?tenant=acme&transaction_id=' . $payment->id)
            ->assertJson(['status' => 'not_found'])
            ->assertJsonMissingPath('wifi_token');
    }

    public function test_polling_cannot_read_another_tenants_transaction(): void
    {
        $victim  = $this->makeTenant('victim');
        $package = $this->makePackage($victim);
        $payment = $this->makePendingPayment($victim, $package, 'ORD-1', [
            'status'       => 'completed',
            'voucher_code' => 'TNSECRET123',
        ]);
        $this->makeTenant('attacker');
        $this->fakeExternalServices();

        $this->getJson('/api/payment/status?tenant=attacker&transaction_id=' . $payment->public_id)
            ->assertJson(['status' => 'not_found'])
            ->assertJsonMissingPath('wifi_token');
    }

    // ── Initiate ─────────────────────────────────────────────────────────────

    public function test_starting_a_payment_returns_an_unguessable_id_and_never_the_gateway_order_id(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['price' => 5000]);
        $this->fakeExternalServices();

        $response = $this->postJson('/api/payment/initiate?tenant=acme', [
            'phone'      => '0712345678',
            'package_id' => $package->id,
        ])->assertOk()->assertJson(['status' => 'success']);

        $this->assertSame(26, strlen($response->json('transaction_id')));
        $response->assertJsonMissingPath('order_id');

        $payment = Transaction::firstOrFail();
        $this->assertSame($payment->public_id, $response->json('transaction_id'));
        $this->assertSame('ORD-NEW', $payment->palmpesa_order_id);
        $this->assertSame(Transaction::CHANNEL_PORTAL, $payment->channel);
        $this->assertSame('pending', $payment->status);
    }

    // ── Reconcile ────────────────────────────────────────────────────────────

    public function test_reconcile_settles_paid_payments_whose_callback_never_arrived(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $old     = $this->makePendingPayment($tenant, $package, 'ORD-OLD');
        $fresh   = $this->makePendingPayment($tenant, $package, 'ORD-FRESH');
        $this->fakeExternalServices(['ORD-OLD' => 'COMPLETED', 'ORD-FRESH' => 'COMPLETED']);

        Transaction::whereKey($old->id)->update(['created_at' => now()->subMinutes(5)]);

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame('completed', $old->fresh()->status);
        $this->assertSame('pending', $fresh->fresh()->status, 'a payment younger than a minute is left to the callback');
        $this->assertSame(5000, TenantWallet::where('tenant_id', $tenant->id)->value('balance'));
    }
}
