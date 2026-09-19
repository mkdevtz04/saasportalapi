<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * The older API-mode setup script and the shared endpoints a router calls during setup.
 * The current one-command setup is covered in RouterConnectivityTest.
 */
class RouterProvisionTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://trinetpay.test']);
    }

    public function test_a_valid_token_downloads_a_script_for_that_tenants_portal(): void
    {
        $tenant = $this->makeTenant('testisp');
        $router = $this->makeRouter($tenant, ['provision_token' => 'trinet_prov_token123']);

        $response = $this->get('/provision/trinet_prov_token123')->assertOk();

        $script = $response->getContent();
        $this->assertStringContainsString('testisp.trinetpay.test', $script);
        $this->assertStringContainsString($router->nas_identifier, $script);
        $this->assertStringContainsString('https://trinetpay.test/provision/trinet_prov_token123/complete', $script);
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));

        $this->assertSame('script_downloaded', $router->fresh()->provision_status);
    }

    public function test_the_script_addresses_come_from_the_configured_url_never_the_request_host(): void
    {
        $tenant = $this->makeTenant('testisp');
        $this->makeRouter($tenant, ['provision_token' => 'trinet_prov_token123']);

        $script = $this->call('GET', 'http://evil.example/provision/trinet_prov_token123')->getContent();

        $this->assertStringNotContainsString('evil.example', $script);
        $this->assertStringContainsString('trinetpay.test', $script);
    }

    public function test_router_names_and_usernames_cannot_break_out_of_the_script(): void
    {
        $tenant = $this->makeTenant('testisp');
        $this->makeRouter($tenant, [
            'name'            => "Shop\n/system reset-configuration\n# ",
            'username'        => 'a"; /user remove admin; "b',
            'password'        => 'p"$(x)\\?',
            'provision_token' => 'trinet_prov_token123',
        ]);

        $script = $this->get('/provision/trinet_prov_token123')->getContent();

        foreach (explode("\n", $script) as $line) {
            $this->assertStringStartsNotWith('/system reset-configuration', trim($line), 'a router name became a command');
            $this->assertStringStartsNotWith('/user remove admin', trim($line));
        }

        // The quote in the username is escaped, so it stays inside the string.
        $this->assertStringContainsString('name="a\"; /user remove admin; \"b"', $script);
        $this->assertStringContainsString('password="p\"\$(x)\\\\\?"', $script);
    }

    public function test_an_unknown_token_gets_an_error_script_and_changes_nothing(): void
    {
        $tenant = $this->makeTenant('testisp');
        $router = $this->makeRouter($tenant, ['provision_token' => 'trinet_prov_token123']);

        $this->get('/provision/wrong-token')
            ->assertNotFound()
            ->assertSee('Invalid or expired provision token', false);

        $this->assertSame('pending', $router->fresh()->provision_status);
    }

    public function test_the_router_calling_back_marks_provisioning_complete(): void
    {
        $tenant = $this->makeTenant('testisp');
        $router = $this->makeRouter($tenant, ['provision_token' => 'trinet_prov_token123']);

        $this->get('/provision/trinet_prov_token123/complete')->assertOk();

        $router->refresh();
        $this->assertSame('completed', $router->provision_status);
        $this->assertSame('online', $router->status);
        $this->assertNotNull($router->provisioned_at);
    }

    public function test_the_status_endpoint_reports_progress_and_hides_unknown_tokens(): void
    {
        $tenant = $this->makeTenant('testisp');
        $this->makeRouter($tenant, ['provision_token' => 'trinet_prov_token123']);

        $this->getJson('/provision/trinet_prov_token123/status')
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'pending', 'router_name' => 'Main router']);

        $this->getJson('/provision/wrong-token/status')->assertNotFound();
    }
}
