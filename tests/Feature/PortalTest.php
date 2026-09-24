<?php

namespace Tests\Feature;

use App\Contracts\SmsGateway;
use App\Jobs\SendReceiptJob;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\PaymentSettlement;
use App\Services\Sms\BeemSmsGateway;
use App\Services\Sms\KilakonaSmsGateway;
use App\Support\HotspotUrl;
use App\Support\Phone;
use App\Support\TenantUrls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\Concerns\BuildsTenants;
use Tests\Concerns\CapturesSms;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use BuildsTenants;
    use CapturesSms;
    use RefreshDatabase;

    // ── One portal per ISP ───────────────────────────────────────────────────

    public function test_each_isp_has_their_own_portal_address_and_sees_only_their_own_packages(): void
    {
        // A key with a hyphen in it is the ordinary case, because it comes from the ISP's name.
        $juma = $this->makeTenant('jagadi-wifi', ['name' => 'Jagadi WiFi']);
        $asha = $this->makeTenant('asha', ['name' => 'Asha WiFi']);
        $this->makePackage($juma, ['name' => 'Jagadi Daily', 'price' => 1000]);
        $this->makePackage($asha, ['name' => 'Asha Daily', 'price' => 2000]);

        $this->get('/portal/jagadi-wifi')->assertOk()->assertSee('Jagadi Daily')->assertDontSee('Asha Daily');
        $this->get('/portal/asha')->assertOk()->assertSee('Asha Daily')->assertDontSee('Jagadi Daily');
    }

    public function test_the_portal_address_of_an_isp_is_their_own_key_on_the_platform_host(): void
    {
        config(['app.url' => 'https://wifikitaa.test']);
        $tenant = $this->makeTenant('juma');

        $this->assertSame('https://wifikitaa.test/portal/juma', TenantUrls::portal($tenant));
        $this->assertSame('wifikitaa.test/portal/juma', TenantUrls::portalLabel($tenant));

        // Portals live on the platform host, so a router only has to let that one through.
        $this->assertSame('wifikitaa.test', TenantUrls::portalHost($tenant));
    }

    public function test_a_payment_started_on_one_isps_portal_belongs_to_that_isp(): void
    {
        $juma    = $this->makeTenant('juma');
        $asha    = $this->makeTenant('asha');
        $package = $this->makePackage($juma, ['price' => 1000]);
        $this->makePackage($asha, ['price' => 2000]);
        $this->fakeExternalServices();

        // The page sends its own key back on every call, the way the portal page does.
        $this->postJson('/api/payment/initiate', ['phone' => '0712345678', 'package_id' => $package->id], ['X-Portal-Tenant' => 'juma'])
            ->assertOk();

        $payment = Transaction::firstOrFail();
        $this->assertSame($juma->id, $payment->tenant_id);
        $this->assertSame(0, $asha->transactions()->count());
    }

    public function test_an_isp_portal_cannot_sell_another_isps_package(): void
    {
        $juma = $this->makeTenant('juma');
        $asha = $this->makeTenant('asha');
        $this->makePackage($juma);
        $ashaPackage = $this->makePackage($asha, ['name' => 'Asha Daily']);
        $this->fakeExternalServices();

        $this->postJson('/api/payment/initiate', ['phone' => '0712345678', 'package_id' => $ashaPackage->id], ['X-Portal-Tenant' => 'juma'])
            ->assertStatus(404);

        $this->assertSame(0, Transaction::count());
    }

    public function test_a_voucher_is_only_accepted_by_the_portal_of_the_isp_that_issued_it(): void
    {
        $juma    = $this->makeTenant('juma');
        $asha    = $this->makeTenant('asha');
        $package = $this->makePackage($juma);
        $this->makeRouter($juma, ['auth_mode' => 'radius', 'router_ip' => null, 'username' => null, 'password' => null]);
        $this->makeRouter($asha, ['auth_mode' => 'radius', 'router_ip' => null, 'username' => null, 'password' => null]);
        Voucher::create(['tenant_id' => $juma->id, 'package_id' => $package->id, 'code' => 'TNJUMA0001']);
        $this->fakeExternalServices();

        $this->postJson('/api/voucher/redeem', ['code' => 'TNJUMA0001'], ['X-Portal-Tenant' => 'asha'])->assertStatus(422);
        $this->assertNull(Voucher::firstOrFail()->used_at);

        $this->postJson('/api/voucher/redeem', ['code' => 'TNJUMA0001'], ['X-Portal-Tenant' => 'juma'])->assertOk();
        $this->assertNotNull(Voucher::firstOrFail()->used_at);
    }

    public function test_a_router_that_names_itself_reaches_its_own_isps_portal(): void
    {
        $juma   = $this->makeTenant('juma');
        $asha   = $this->makeTenant('asha');
        $router = $this->makeRouter($juma);
        $this->makeRouter($asha);
        $this->makePackage($juma, ['name' => 'Juma Daily']);
        $this->makePackage($asha, ['name' => 'Asha Daily']);

        $this->get('/portal?nas=' . $router->nas_identifier)
            ->assertOk()
            ->assertSee('Juma Daily')
            ->assertDontSee('Asha Daily');
    }

    public function test_an_unknown_or_empty_router_name_never_falls_through_to_another_isp(): void
    {
        $tenant = $this->makeTenant('juma');
        $this->makeRouter($tenant, ['nas_identifier' => '']);
        $this->makePackage($tenant, ['name' => 'Juma Daily']);

        foreach (['/portal', '/portal?nas=', '/portal?nas=nas-does-not-exist'] as $link) {
            $this->get($link)->assertOk()->assertDontSee('Juma Daily');
        }
    }

    public function test_a_portal_link_that_names_no_isp_refuses_to_sell_instead_of_showing_a_blank_shop(): void
    {
        $this->makeTenant('juma');

        $this->get('/portal')
            ->assertOk()
            ->assertSee('This link is not connected to a provider')
            ->assertSee('Kiungo hiki hakijaunganishwa');

        $this->postJson('/api/payment/initiate', ['phone' => '0712345678', 'package_id' => 1])->assertStatus(422);
        $this->postJson('/api/voucher/redeem', ['code' => 'TNJUMA0001'])->assertStatus(404);
    }

    public function test_a_portal_key_that_belongs_to_nobody_sells_nothing(): void
    {
        $this->makeTenant('juma');

        $this->get('/portal/notanisp')->assertOk()->assertSee('This link is not connected to a provider');
    }

    public function test_naming_an_isp_in_a_request_never_reaches_their_dashboard(): void
    {
        $juma  = $this->makeTenant('juma');
        $asha  = $this->makeTenant('asha');
        $owner = $this->makeOwner($asha);
        $this->makePackage($juma, ['name' => 'Juma Daily']);
        $this->actingAs($owner, 'tenant');

        $this->get('/dashboard/packages?tenant=juma')->assertOk()->assertDontSee('Juma Daily');
        $this->get('/dashboard/packages', ['X-Portal-Tenant' => 'juma'])->assertOk()->assertDontSee('Juma Daily');
    }

    public function test_a_suspended_isp_sells_nothing_on_their_own_portal_address(): void
    {
        $tenant = $this->makeTenant('juma', ['status' => 'suspended']);
        $this->makePackage($tenant, ['name' => 'Juma Daily']);

        $this->get('/portal/juma')->assertStatus(503)->assertDontSee('Juma Daily');
    }

    // ── Codes a customer holds ───────────────────────────────────────────────

    public function test_a_dashboard_voucher_typed_on_the_routers_login_page_is_redeemed_by_the_portal(): void
    {
        $tenant  = $this->makeTenant('jagadi-wifi');
        $package = $this->makePackage($tenant);
        $this->makeRouter($tenant, ['auth_mode' => 'radius', 'router_ip' => null, 'username' => null, 'password' => null]);
        Voucher::create(['tenant_id' => $tenant->id, 'package_id' => $package->id, 'code' => 'TNPRINT001']);
        $this->fakeExternalServices();

        // The router's login page sends the code here, because the router has never heard of it.
        $this->get('/portal/jagadi-wifi?code=tnprint001')->assertOk()->assertSee('TNPRINT001');

        $this->postJson('/api/voucher/redeem', ['code' => 'TNPRINT001'], ['X-Portal-Tenant' => 'jagadi-wifi'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNotNull(Voucher::firstOrFail()->used_at);
    }

    public function test_a_code_in_the_link_is_cleaned_before_it_reaches_the_page(): void
    {
        $this->makeTenant('jagadi-wifi');

        $this->get('/portal/jagadi-wifi?code=' . urlencode('"><script>alert(1)</script>'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_code_from_a_payment_still_works_when_the_customer_comes_back_with_it(): void
    {
        $tenant  = $this->makeTenant('jagadi-wifi');
        $package = $this->makePackage($tenant, ['name' => 'Daily']);
        $this->makeRouter($tenant);
        $this->makePendingPayment($tenant, $package, 'ORD-1', [
            'status' => 'completed', 'voucher_code' => 'TNPAID0001', 'expires_at' => now()->addDay(),
        ]);
        $this->fakeExternalServices();

        // It is not a voucher, so redeeming must not call it invalid: the customer paid for it.
        $this->postJson('/api/voucher/redeem', ['code' => 'TNPAID0001'], ['X-Portal-Tenant' => 'jagadi-wifi', 'X-Portal-Lang' => 'en'])
            ->assertOk()
            ->assertJson(['ok' => true, 'code' => 'TNPAID0001', 'package' => 'Daily', 'access_ready' => true]);
    }

    public function test_a_code_whose_time_has_run_out_is_refused(): void
    {
        $tenant  = $this->makeTenant('jagadi-wifi');
        $package = $this->makePackage($tenant);
        $this->makeRouter($tenant);
        $this->makePendingPayment($tenant, $package, 'ORD-1', [
            'status' => 'completed', 'voucher_code' => 'TNEXPIRED1', 'expires_at' => now()->subHour(),
        ]);
        $this->fakeExternalServices();

        $this->postJson('/api/voucher/redeem', ['code' => 'TNEXPIRED1'], ['X-Portal-Tenant' => 'jagadi-wifi'])
            ->assertStatus(422);
    }

    public function test_an_active_code_of_one_isp_is_not_accepted_by_another(): void
    {
        $jagadi  = $this->makeTenant('jagadi-wifi');
        $asha    = $this->makeTenant('asha');
        $package = $this->makePackage($jagadi);
        $this->makeRouter($jagadi);
        $this->makeRouter($asha);
        $this->makePendingPayment($jagadi, $package, 'ORD-1', [
            'status' => 'completed', 'voucher_code' => 'TNPAID0001', 'expires_at' => now()->addDay(),
        ]);
        $this->fakeExternalServices();

        $this->postJson('/api/voucher/redeem', ['code' => 'TNPAID0001'], ['X-Portal-Tenant' => 'asha'])
            ->assertStatus(422);
    }

    public function test_a_code_typed_on_the_routers_login_page_goes_to_the_portal(): void
    {
        config(['app.url' => 'https://wifikitaa.test']);
        $tenant = $this->makeTenant('testisp');
        $router = $this->radiusRouterFor($tenant);

        $html = $this->get('/provision/' . $router->provision_token . '/login.html')->assertOk()->getContent();

        $this->assertStringContainsString('<form method="get" action="https://wifikitaa.test/portal/testisp">', $html);
        $this->assertStringContainsString('name="code"', $html);
        $this->assertStringContainsString('value="' . $router->nas_identifier . '"', $html);

        // Posting a typed code to the router is what failed for vouchers the ISP had just printed:
        // the router only knows a code once the platform has given it to the router.
        // It forwards the router's login address to the portal, but sends no login of its own.
        $typed = \Illuminate\Support\Str::before(\Illuminate\Support\Str::after($html, '<form method="get"'), '</form>');
        $this->assertStringNotContainsString('name="username"', $typed);
        $this->assertStringNotContainsString('name="password"', $typed);
    }

    public function test_the_routers_login_page_reconnects_a_device_that_has_been_here_before(): void
    {
        $router = $this->radiusRouterFor($this->makeTenant('testisp'));

        $html = $this->get('/provision/' . $router->provision_token . '/login.html')->assertOk()->getContent();

        // The one thing posted to the router itself: a code this browser saw work here before.
        // It is the router's own address, which no other page is sure of.
        $this->assertStringContainsString('<form id="reconnect" method="post" action="$(link-login-only)">', $html);
        $this->assertStringContainsString("localStorage.getItem(KEY)", $html);

        // A code the router has just refused is dropped, so the page cannot loop on it.
        $this->assertStringContainsString('localStorage.removeItem(KEY)', $html);
    }

    public function test_the_page_shown_after_a_login_keeps_the_code_on_the_customers_device(): void
    {
        $router = $this->radiusRouterFor($this->makeTenant('testisp'));

        $html = $this->get('/provision/' . $router->provision_token . '/alogin.html')->assertOk()->getContent();

        $this->assertStringContainsString('data-user="$(username)"', $html);
        $this->assertStringContainsString("localStorage.setItem('trinetpay-code', user)", $html);

        // A device let back in by its MAC address logs in as that address, which is not a code.
        $this->assertStringContainsString('/^[A-Za-z0-9]{4,32}$/.test(user)', $html);
    }

    public function test_the_page_shown_after_a_login_is_not_served_without_a_provision_token(): void
    {
        $this->radiusRouterFor($this->makeTenant('testisp'));

        $this->get('/provision/not-a-real-token/alogin.html')->assertNotFound();
    }

    private function radiusRouterFor(\App\Models\Tenant $tenant): \App\Models\TenantRouter
    {
        return $this->makeRouter($tenant, [
            'auth_mode'       => 'radius',
            'router_ip'       => null,
            'username'        => null,
            'password'        => null,
            'provision_token' => 'trinet_prov_login',
        ]);
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

    public function test_the_kilakona_gateway_sends_what_it_was_given_and_reports_the_answer(): void
    {
        Http::fake([
            'sms.kilakona.test/*' => Http::sequence()
                ->push(['success' => true])
                ->push(['success' => false, 'message' => 'Invalid'], 200)
                ->push('down', 500),
        ]);
        $gateway = new KilakonaSmsGateway('key', 'secret', 'mktech', 'https://sms.kilakona.test/send');

        $this->assertTrue($gateway->send('255695493670', 'Hello'));
        $this->assertFalse($gateway->send('255695493670', 'Hello'));
        $this->assertFalse($gateway->send('255695493670', 'Hello'));

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $request->url() === 'https://sms.kilakona.test/send'
                && $request->hasHeader('api_key', 'key')
                && $request->hasHeader('api_secret', 'secret')
                && $data['senderName'] === 'mktech'
                && $data['recipientNumber'] === '255695493670'
                && $data['message'] === 'Hello';
        });
    }

    public function test_kilakona_sends_nothing_at_all_until_its_address_is_configured(): void
    {
        Http::fake();

        $this->assertFalse((new KilakonaSmsGateway('key', 'secret', 'mktech', ''))->send('255695493670', 'Hi'));
        Http::assertNothingSent();
    }

    public function test_kilakona_never_throws_when_the_network_is_down(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('offline'));

        $this->assertFalse((new KilakonaSmsGateway('key', 'secret', 'mktech', 'https://sms.kilakona.test/send'))->send('255695493670', 'Hi'));
    }

    public function test_choosing_kilakona_in_the_config_selects_that_gateway(): void
    {
        config(['sms.driver' => 'kilakona', 'sms.kilakona.endpoint' => 'https://sms.kilakona.test/send']);

        $this->assertInstanceOf(KilakonaSmsGateway::class, $this->app->make(SmsGateway::class));
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
