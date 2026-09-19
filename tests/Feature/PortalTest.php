<?php

namespace Tests\Feature;

use App\Contracts\SmsGateway;
use App\Jobs\SendReceiptJob;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\PaymentSettlement;
use App\Services\Sms\BeemSmsGateway;
use App\Support\HotspotUrl;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    /** An SMS gateway that remembers what it was asked to send. */
    private function captureSms(bool $accept = true): object
    {
        $fake = new class ($accept) implements SmsGateway {
            public array $sent = [];

            public function __construct(private bool $accept)
            {
            }

            public function send(string $to, string $message): bool
            {
                $this->sent[] = ['to' => $to, 'message' => $message];

                return $this->accept;
            }
        };

        $this->app->instance(SmsGateway::class, $fake);

        return $fake;
    }

    // ── Login address safety ─────────────────────────────────────────────────

    public function test_only_local_router_addresses_are_accepted_as_the_login_address(): void
    {
        foreach ([
            'http://192.168.88.1/login',
            'http://10.5.50.1/login',
            'https://172.16.0.1/login',
            'http://hotspot.lan/login',
            'http://hotspot/login',
            'http://login.wifi/login',
            'http://10.5.50.1:8080/login',
            'https://Hotspot.LAN/login',
        ] as $ok) {
            $this->assertSame($ok, HotspotUrl::loginUrl($ok), $ok . ' should be accepted');
        }

        foreach ([
            'https://evil.example/login',
            'http://8.8.8.8/login',
            'http://evil.example@192.168.1.1/login',
            'http://192.168.1.1@evil.example/',
            'javascript:alert(1)',
            '//evil.example/login',
            'ftp://192.168.1.1/',
            'http://192.168.1.1/login" onload="x',
            "http://192.168.1.1/\nHost: evil",
            // The same public address written in the forms a browser still understands.
            'http://134744072/login',            // 8.8.8.8 as one decimal number
            'http://0x08080808/login',           // 8.8.8.8 as hex
            'http://010.8.8.8/login',            // octal-looking part
            'http://[::ffff:8.8.8.8]/login',     // IPv4 inside IPv6
            'http://[::1]/login',
            'http://[fd00::1]/login',
            'http://8.8.8.8./login',             // trailing dot
            'http://192.168.1.1.evil.example/login',
            'http://hotspot.evil.example/login',
            'http://1/login',
            'http://a b/login',
            '',
            null,
        ] as $bad) {
            $this->assertNull(HotspotUrl::loginUrl($bad), var_export($bad, true) . ' should be refused');
        }
    }

    public function test_the_page_the_customer_wanted_can_be_any_web_address_but_never_a_script(): void
    {
        $this->assertSame('http://www.google.com/', HotspotUrl::destination('http://www.google.com/'));
        $this->assertNull(HotspotUrl::destination('javascript:alert(1)'));
        $this->assertNull(HotspotUrl::destination('data:text/html,<script>1</script>'));
    }

    public function test_a_forged_login_address_in_the_portal_link_is_dropped(): void
    {
        $tenant = $this->makeTenant();
        $this->makePackage($tenant);

        $forged = $this->get('/portal?tenant=acme&link-login-only=' . urlencode('https://evil.example/login'))->assertOk();
        $forged->assertDontSee('evil.example', false);

        $real = $this->get('/portal?tenant=acme&link-login-only=' . urlencode('http://192.168.88.1/login'))->assertOk();
        $real->assertSee('192.168.88.1', false);
    }

    public function test_a_forged_login_address_sent_when_paying_is_never_used_to_return_the_credentials(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->makeRouter($tenant);
        $this->fakeExternalServices();

        $id = $this->postJson('/api/payment/initiate?tenant=acme', [
            'phone' => '0712345678', 'package_id' => $package->id,
            'link_login_only' => 'https://evil.example/login', 'link_orig' => 'javascript:alert(1)',
        ])->assertOk()->json('transaction_id');

        $this->fakeExternalServices(['ORD-NEW' => 'COMPLETED']);

        $this->getJson('/api/payment/status?tenant=acme&transaction_id=' . $id)
            ->assertJson(['status' => 'paid', 'login_url' => null, 'dst' => null]);
    }

    public function test_query_values_shown_in_the_page_are_kept_short_and_printable(): void
    {
        $this->makeTenant();

        $this->get('/portal?tenant=acme&mac=' . urlencode("AA:BB\x01") . '&error=' . urlencode(str_repeat('x', 500)))
            ->assertOk()
            ->assertDontSee(str_repeat('x', 201), false);
    }

    // ── Language ─────────────────────────────────────────────────────────────

    public function test_the_portal_opens_in_the_ISPs_default_language_and_the_customer_can_switch(): void
    {
        $tenant = $this->makeTenant();
        $tenant->settings()->create(['brand_color' => '#0b7a75', 'default_language' => 'sw']);

        $this->get('/portal?tenant=acme')->assertOk()->assertSee('Lipa mtandaoni')->assertDontSee('Pay online');

        $this->get('/portal?tenant=acme&lang=en')->assertOk()->assertSee('Pay online')->assertDontSee('Lipa mtandaoni');
    }

    public function test_an_english_isp_default_is_respected_and_an_explicit_choice_wins(): void
    {
        $tenant = $this->makeTenant();
        $tenant->settings()->create(['brand_color' => '#0b7a75', 'default_language' => 'en']);

        $this->get('/portal?tenant=acme')->assertSee('Pay online');
        $this->get('/portal?tenant=acme&lang=sw')->assertSee('Lipa mtandaoni');
    }

    public function test_an_unsupported_language_falls_back_safely(): void
    {
        $tenant = $this->makeTenant();
        $tenant->settings()->create(['brand_color' => '#0b7a75', 'default_language' => 'en']);

        $this->get('/portal?tenant=acme&lang=../../etc/passwd')->assertOk()->assertSee('Pay online');
    }

    public function test_replies_to_the_portal_page_come_back_in_the_language_it_asks_for(): void
    {
        $this->makeTenant();
        $this->fakeExternalServices();

        $this->postJson('/api/voucher/redeem?tenant=acme', ['code' => 'NOSUCHCODE'], ['X-Portal-Lang' => 'sw'])
            ->assertStatus(422)->assertJson(['message' => 'Namba ya vocha si sahihi au imeshatumika.']);

        $this->postJson('/api/voucher/redeem?tenant=acme', ['code' => 'NOSUCHCODE'], ['X-Portal-Lang' => 'en'])
            ->assertStatus(422)->assertJson(['message' => 'This voucher code is not valid or was already used.']);
    }

    public function test_the_language_files_have_the_same_keys(): void
    {
        foreach (['portal', 'sms'] as $file) {
            $en = array_keys(require lang_path("en/{$file}.php"));
            $sw = array_keys(require lang_path("sw/{$file}.php"));

            $this->assertSame([], array_diff($en, $sw), "{$file}: missing in Swahili");
            $this->assertSame([], array_diff($sw, $en), "{$file}: missing in English");
        }
    }

    // ── Paying ───────────────────────────────────────────────────────────────

    public function test_tapping_pay_twice_gives_one_transaction_and_one_prompt(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->fakeExternalServices();

        $payload = ['phone' => '0712345678', 'package_id' => $package->id];
        $first   = $this->postJson('/api/payment/initiate?tenant=acme', $payload)->assertOk()->json('transaction_id');
        $second  = $this->postJson('/api/payment/initiate?tenant=acme', $payload)->assertOk()->json('transaction_id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, Http::recorded(fn (Request $r) => str_contains($r->url(), 'pay-via-mobile'))->count());
    }

    public function test_the_language_of_the_customer_is_kept_with_the_payment(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->fakeExternalServices();

        $this->postJson('/api/payment/initiate?tenant=acme', ['phone' => '0712345678', 'package_id' => $package->id], ['X-Portal-Lang' => 'sw'])
            ->assertOk()->assertJson(['message' => 'Ombi la malipo limetumwa kwenye simu yako!']);

        $this->assertSame('sw', Transaction::firstOrFail()->locale);
    }

    public function test_a_gateway_error_never_reaches_the_customer_and_the_payment_is_marked_failed(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        config(['services.palmpesa.base_url' => 'https://palmpesa.test']);
        Http::fake(['palmpesa.test/*' => Http::response(['message' => 'SECRET INTERNAL DETAIL'], 500)]);

        $this->postJson('/api/payment/initiate?tenant=acme', ['phone' => '0712345678', 'package_id' => $package->id])
            ->assertStatus(500)
            ->assertDontSee('SECRET INTERNAL DETAIL', false);

        $this->assertSame('failed', Transaction::firstOrFail()->status);
    }

    public function test_a_gateway_reply_without_an_order_id_fails_the_payment_instead_of_hanging(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        config(['services.palmpesa.base_url' => 'https://palmpesa.test']);
        Http::fake(['palmpesa.test/*' => Http::response([])]);

        $this->postJson('/api/payment/initiate?tenant=acme', ['phone' => '0712345678', 'package_id' => $package->id])
            ->assertStatus(500);

        $this->assertSame('failed', Transaction::firstOrFail()->status);
    }

    public function test_one_phone_number_cannot_be_flooded_with_payment_prompts(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        config(['services.palmpesa.base_url' => 'https://palmpesa.test']);
        Http::fake(['palmpesa.test/*' => Http::response(['message' => 'down'], 500)]);
        RateLimiter::clear('pay-phone:255712345678');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/payment/initiate?tenant=acme', ['phone' => '0712345678', 'package_id' => $package->id])->assertStatus(500);
        }

        $this->postJson('/api/payment/initiate?tenant=acme', ['phone' => '+255 712 345 678', 'package_id' => $package->id])
            ->assertStatus(429);
        $this->assertSame(5, Http::recorded(fn (Request $r) => str_contains($r->url(), 'pay-via-mobile'))->count(), 'the sixth request never reached the gateway');
    }

    public function test_status_replies_carry_a_reference_the_customer_can_quote(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices();

        $reference = $this->getJson('/api/payment/status?tenant=acme&transaction_id=' . $payment->public_id)->json('reference');

        $this->assertSame(8, strlen($reference));
        $this->assertSame($reference, strtoupper(substr($payment->public_id, -8)));
        $this->assertSame($reference, $payment->reference());
    }

    public function test_a_returning_device_sees_its_active_session(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['name' => 'Weekly']);
        $this->makePendingPayment($tenant, $package, 'ORD-1', [
            'status' => 'completed', 'voucher_code' => 'TNBACK12345',
            'customer_mac' => 'AA:BB:CC:DD:EE:FF', 'expires_at' => now()->addDay(),
        ]);

        $this->get('/portal?tenant=acme&lang=en&mac=AA:BB:CC:DD:EE:FF')
            ->assertOk()
            ->assertSee('Welcome back!')
            ->assertSee('TNBACK12345')
            ->assertSee('Weekly');

        // The translation table in every page contains the words, so look for the token instead.
        $this->get('/portal?tenant=acme&lang=en&mac=11:22:33:44:55:66')->assertOk()->assertDontSee('TNBACK12345');
    }

    // ── SMS receipts ─────────────────────────────────────────────────────────

    public function test_a_paid_customer_gets_a_receipt_in_their_language(): void
    {
        $sms     = $this->captureSms();
        $tenant  = $this->makeTenant('acme', ['name' => 'Juma WiFi']);
        $package = $this->makePackage($tenant, ['name' => 'Daily', 'price' => 5000]);
        $this->makeRouter($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1', ['locale' => 'sw', 'phone' => '0712 345 678']);
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);

        app(PaymentSettlement::class)->verifyAndSettle($payment);

        $payment->refresh();
        $this->assertCount(1, $sms->sent);
        $this->assertSame('255712345678', $sms->sent[0]['to']);
        $this->assertStringContainsString('Umelipa TZS 5,000 kwa Daily', $sms->sent[0]['message']);
        $this->assertStringContainsString($payment->voucher_code, $sms->sent[0]['message']);
        $this->assertStringContainsString('Juma WiFi', $sms->sent[0]['message']);
        $this->assertStringContainsString($payment->reference(), $sms->sent[0]['message']);
        $this->assertNotNull($payment->receipt_sent_at);
    }

    public function test_an_english_customer_gets_an_english_receipt(): void
    {
        $sms     = $this->captureSms();
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['price' => 5000]);
        $this->makeRouter($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1', ['locale' => 'en']);
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);

        app(PaymentSettlement::class)->verifyAndSettle($payment);

        $this->assertStringContainsString('You paid TZS 5,000', $sms->sent[0]['message']);
    }

    public function test_the_receipt_is_sent_only_once_even_if_the_job_runs_again(): void
    {
        $sms     = $this->captureSms();
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->makeRouter($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);

        app(PaymentSettlement::class)->verifyAndSettle($payment);
        (new SendReceiptJob($payment->id))->handle($this->app->make(SmsGateway::class));
        (new SendReceiptJob($payment->id))->handle($this->app->make(SmsGateway::class));

        $this->assertCount(1, $sms->sent);
    }

    public function test_no_receipt_for_vouchers_unpaid_transactions_or_numbers_that_are_not_mobiles(): void
    {
        $sms     = $this->captureSms();
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);

        $voucherSale = $this->makePendingPayment($tenant, $package, null, ['status' => 'completed', 'channel' => 'voucher', 'voucher_code' => 'TNV1']);
        $pending     = $this->makePendingPayment($tenant, $package, 'ORD-2');
        $landline    = $this->makePendingPayment($tenant, $package, 'ORD-3', ['status' => 'completed', 'voucher_code' => 'TNL1', 'phone' => '022 212 3456']);

        foreach ([$voucherSale, $pending, $landline] as $transaction) {
            (new SendReceiptJob($transaction->id))->handle($this->app->make(SmsGateway::class));
        }

        $this->assertSame([], $sms->sent);
    }

    public function test_a_provider_failure_is_retried_and_never_marks_the_receipt_as_sent(): void
    {
        $this->captureSms(accept: false);
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1', ['status' => 'completed', 'voucher_code' => 'TNOK1']);

        $this->expectException(RuntimeException::class);

        try {
            (new SendReceiptJob($payment->id))->handle($this->app->make(SmsGateway::class));
        } finally {
            $this->assertNull($payment->fresh()->receipt_sent_at);
        }
    }

    public function test_a_broken_sms_provider_never_breaks_the_payment(): void
    {
        $this->captureSms(accept: false);
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant, ['price' => 5000]);
        $this->makeRouter($tenant);
        $payment = $this->makePendingPayment($tenant, $package, 'ORD-1');
        $this->fakeExternalServices(['ORD-1' => 'COMPLETED']);

        app(PaymentSettlement::class)->verifyAndSettle($payment);

        $this->assertSame('completed', $payment->fresh()->status);
    }

    public function test_the_beem_gateway_sends_the_documented_request_and_reports_the_answer(): void
    {
        Http::fake([
            'apisms.beem.africa/*' => Http::sequence()
                ->push(['successful' => true, 'code' => 100])
                ->push(['successful' => false, 'code' => 102, 'message' => 'Invalid'], 200)
                ->push('down', 500),
        ]);
        $gateway = new BeemSmsGateway('key', 'secret', 'TRINETPAY');

        $this->assertTrue($gateway->send('255712345678', 'Hello'));
        $this->assertFalse($gateway->send('255712345678', 'Hello'));
        $this->assertFalse($gateway->send('255712345678', 'Hello'));

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $request->url() === 'https://apisms.beem.africa/v1/send'
                && $request->hasHeader('Authorization', 'Basic ' . base64_encode('key:secret'))
                && $data['source_addr'] === 'TRINETPAY'
                && $data['message'] === 'Hello'
                && $data['recipients'][0]['dest_addr'] === '255712345678';
        });
    }

    public function test_the_beem_gateway_never_throws_when_the_network_is_down(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('offline'));

        $this->assertFalse((new BeemSmsGateway('key', 'secret', 'TRINETPAY'))->send('255712345678', 'Hello'));
    }

    public function test_the_default_sms_driver_sends_nothing(): void
    {
        config(['sms.driver' => 'log']);

        $this->assertInstanceOf(\App\Services\Sms\LogSmsGateway::class, $this->app->make(SmsGateway::class));
        Http::assertNothingSent();
    }

    // ── Phone numbers ────────────────────────────────────────────────────────

    public function test_phone_numbers_are_put_in_international_form(): void
    {
        $this->assertSame('255712345678', Phone::international('0712 345 678'));
        $this->assertSame('255712345678', Phone::international('+255 712-345-678'));
        $this->assertSame('255612345678', Phone::international('612345678'));
        $this->assertSame('255712345678', Phone::international('255712345678'));

        $this->assertTrue(Phone::isMobile('0712345678'));
        $this->assertFalse(Phone::isMobile('0222123456'));
        $this->assertFalse(Phone::isMobile('12345'));
    }

    // ── Dashboard ────────────────────────────────────────────────────────────

    public function test_an_owner_can_set_the_default_portal_language(): void
    {
        $tenant = $this->makeTenant();
        $tenant->settings()->create(['brand_color' => '#0b7a75']);
        $this->actingAs($this->makeOwner($tenant), 'tenant');

        $this->post('/dashboard/settings', ['brand_color' => '#0b7a75', 'default_language' => 'en'])
            ->assertSessionHasNoErrors();
        $this->assertSame('en', $tenant->settings()->first()->default_language);

        $this->post('/dashboard/settings', ['brand_color' => '#0b7a75', 'default_language' => 'fr'])
            ->assertSessionHasErrors('default_language');
    }

    public function test_the_transactions_page_finds_a_payment_by_the_reference_and_shows_access_problems(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $good    = $this->makePendingPayment($tenant, $package, 'ORD-1', ['status' => 'completed', 'voucher_code' => 'TNGOOD1', 'provision_status' => 'done']);
        $bad     = $this->makePendingPayment($tenant, $package, 'ORD-2', ['status' => 'completed', 'voucher_code' => 'TNBAD1', 'provision_status' => 'failed', 'provision_error' => 'router down']);
        $this->actingAs($this->makeOwner($tenant), 'tenant');

        $this->get('/dashboard/transactions?ref=' . strtolower($bad->reference()))
            ->assertOk()
            ->assertSee('TNBAD1')
            ->assertSee('Needs help')
            ->assertDontSee('TNGOOD1');

        $this->get('/dashboard/transactions')->assertSee('Online')->assertSee('Needs help');
    }

    public function test_the_transactions_page_can_show_only_voucher_sales(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->makePendingPayment($tenant, $package, 'ORD-1', ['status' => 'completed', 'voucher_code' => 'TNPORTAL1']);
        $this->makePendingPayment($tenant, $package, null, ['status' => 'completed', 'channel' => 'voucher', 'voucher_code' => 'TNVOUCHER1']);
        $this->actingAs($this->makeOwner($tenant), 'tenant');

        $this->get('/dashboard/transactions?channel=voucher')->assertSee('TNVOUCHER1')->assertDontSee('TNPORTAL1');
    }

    public function test_redeeming_still_works_end_to_end_from_the_portal(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $this->makeRouter($tenant, ['auth_mode' => 'radius', 'router_ip' => null, 'username' => null, 'password' => null]);
        Voucher::create(['tenant_id' => $tenant->id, 'package_id' => $package->id, 'code' => 'TNEND2END1']);
        $this->fakeExternalServices();

        $this->postJson('/api/voucher/redeem?tenant=acme', ['code' => 'tnend2end1'], ['X-Portal-Lang' => 'en'])
            ->assertOk()
            ->assertJson(['ok' => true, 'message' => 'Voucher accepted! Connecting you now…']);
    }
}
