<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Services\CustomerDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class CustomerDirectoryTest extends TestCase
{
    use RefreshDatabase;
    use BuildsTenants;

    /** @param array<string,mixed> $overrides */
    private function sale(int $tenantId, int $packageId, array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'tenant_id'  => $tenantId,
            'package_id' => $packageId,
            'phone'      => '0712345678',
            'amount'     => 5000,
            'status'     => 'completed',
            'channel'    => Transaction::CHANNEL_PORTAL,
            'expires_at' => now()->addHours(6),
        ], $overrides));
    }

    public function test_the_sales_of_one_phone_number_become_one_customer(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);

        $this->sale($tenant->id, $package->id, ['customer_mac' => 'AA:BB:CC:00:00:01', 'created_at' => now()->subDays(3)]);
        $this->sale($tenant->id, $package->id, ['customer_mac' => 'AA:BB:CC:00:00:02', 'amount' => 2000]);

        $customers = app(CustomerDirectory::class)->list($tenant->id);

        $this->assertCount(1, $customers, 'one person, two devices, two purchases');
        $this->assertSame('0712345678', $customers[0]->identity);
        $this->assertSame(2, $customers[0]->purchases);
        $this->assertSame(7000, $customers[0]->spent);
        $this->assertSame(2, $customers[0]->devices);
        $this->assertTrue($customers[0]->active);
    }

    /** A voucher redeemed without a phone number still belongs to the device that used it. */
    public function test_a_sale_with_no_phone_is_identified_by_its_device(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);

        $this->sale($tenant->id, $package->id, [
            'phone' => '', 'customer_mac' => 'DD:EE:FF:00:00:09', 'channel' => Transaction::CHANNEL_VOUCHER,
        ]);

        $customers = app(CustomerDirectory::class)->list($tenant->id);

        $this->assertCount(1, $customers);
        $this->assertSame('DD:EE:FF:00:00:09', $customers[0]->identity);
        $this->assertNull($customers[0]->phone, 'nothing is invented for a customer who gave no number');
    }

    /** A sale naming nobody is counted, not quietly dropped and not shown as a person. */
    public function test_a_sale_with_neither_phone_nor_device_is_counted_separately(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);

        $this->sale($tenant->id, $package->id, ['phone' => '', 'customer_mac' => null]);

        $this->assertCount(0, app(CustomerDirectory::class)->list($tenant->id));
        $this->assertSame(1, app(CustomerDirectory::class)->summary($tenant->id)['unidentified']);
    }

    public function test_an_expired_customer_is_not_counted_as_having_access(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);

        $this->sale($tenant->id, $package->id, ['phone' => '0711111111', 'expires_at' => now()->subHour()]);
        $this->sale($tenant->id, $package->id, ['phone' => '0722222222', 'expires_at' => now()->addHour()]);

        $summary = app(CustomerDirectory::class)->summary($tenant->id);

        $this->assertSame(2, $summary['customers']);
        $this->assertSame(1, $summary['active']);

        $active = app(CustomerDirectory::class)->list($tenant->id, ['status' => 'active']);
        $this->assertCount(1, $active);
        $this->assertSame('0722222222', $active[0]->identity);
    }

    public function test_searching_finds_a_customer_by_phone_device_or_voucher_code(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);

        $this->sale($tenant->id, $package->id, ['phone' => '0755000111', 'customer_mac' => '11:22:33:44:55:66', 'voucher_code' => 'TNFIND1']);
        $this->sale($tenant->id, $package->id, ['phone' => '0788999000', 'customer_mac' => '99:88:77:66:55:44']);

        $directory = app(CustomerDirectory::class);

        $this->assertSame('0755000111', $directory->list($tenant->id, ['search' => '5000111'])[0]->identity);
        $this->assertSame('0755000111', $directory->list($tenant->id, ['search' => '11:22:33'])[0]->identity);
        $this->assertSame('0755000111', $directory->list($tenant->id, ['search' => 'tnfind1'])[0]->identity);
        $this->assertCount(0, $directory->list($tenant->id, ['search' => 'nobody']));
    }

    public function test_a_profile_gathers_the_devices_purchases_and_failed_attempts(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $router  = $this->makeRouter($tenant);

        $this->sale($tenant->id, $package->id, ['customer_mac' => 'AA:AA:AA:AA:AA:AA', 'router_id' => $router->id]);
        $this->sale($tenant->id, $package->id, ['customer_mac' => 'BB:BB:BB:BB:BB:BB', 'router_id' => $router->id]);
        $this->sale($tenant->id, $package->id, ['status' => 'failed', 'amount' => 5000, 'expires_at' => null]);

        $profile = app(CustomerDirectory::class)->profile($tenant->id, '0712345678');

        $this->assertSame('0712345678', $profile['phone']);
        $this->assertSame(2, $profile['purchases'], 'the failed attempt is not a purchase');
        $this->assertSame(10000, $profile['spent']);
        $this->assertCount(3, $profile['sales'], 'but it is still listed, so a complaint can be traced');
        $this->assertCount(2, $profile['devices']);
        $this->assertTrue($profile['active']);
    }

    public function test_one_isp_cannot_read_another_isps_customer(): void
    {
        $mine   = $this->makeTenant('mine');
        $theirs = $this->makeTenant('theirs');
        $this->sale($theirs->id, $this->makePackage($theirs)->id, ['phone' => '0799888777']);

        $this->assertCount(0, app(CustomerDirectory::class)->list($mine->id));
        $this->assertNull(app(CustomerDirectory::class)->profile($mine->id, '0799888777'));
    }

    // ── The pages ────────────────────────────────────────────────────────────

    public function test_the_owner_sees_the_directory_and_can_open_a_customer(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $owner   = $this->makeOwner($tenant);
        $this->sale($tenant->id, $package->id, ['customer_mac' => 'AA:BB:CC:DD:EE:FF']);

        $this->actingAs($owner, 'tenant')
            ->get('/dashboard/customers')
            ->assertOk()
            ->assertSee('0712345678')
            ->assertSee('AA:BB:CC:DD:EE:FF');

        $this->actingAs($owner, 'tenant')
            ->get('/dashboard/customers/view?id=0712345678')
            ->assertOk()
            ->assertSee('AA:BB:CC:DD:EE:FF')
            ->assertSee('Daily');
    }

    public function test_opening_a_customer_who_does_not_exist_is_a_404(): void
    {
        $tenant = $this->makeTenant();
        $owner  = $this->makeOwner($tenant);

        $this->actingAs($owner, 'tenant')->get('/dashboard/customers/view?id=0700000000')->assertNotFound();
        $this->actingAs($owner, 'tenant')->get('/dashboard/customers/view')->assertNotFound();
    }

    public function test_the_export_walks_every_page_of_the_directory(): void
    {
        $tenant  = $this->makeTenant();
        $package = $this->makePackage($tenant);
        $owner   = $this->makeOwner($tenant);

        // More customers than fit on one page, so a broken loop would lose the rest.
        for ($i = 0; $i < CustomerDirectory::PER_PAGE + 5; $i++) {
            $this->sale($tenant->id, $package->id, ['phone' => '07' . str_pad((string) $i, 8, '0', STR_PAD_LEFT)]);
        }

        $csv = $this->actingAs($owner, 'tenant')->get('/dashboard/customers/export')->streamedContent();

        $this->assertSame(CustomerDirectory::PER_PAGE + 5 + 1, substr_count(trim($csv), "\n") + 1, 'a header and every customer');
        $this->assertStringContainsString('Phone,"Device MAC"', $csv);
    }

    public function test_an_agent_cannot_reach_the_customer_directory(): void
    {
        $tenant = $this->makeTenant();
        $agent  = $this->makeAgent($tenant);

        // Agents sell at the counter. Who an ISP's customers are, and what they spend, is the
        // owner's business, so the dashboard refuses rather than redirecting.
        $this->actingAs($agent, 'tenant')->get('/dashboard/customers')->assertForbidden();
    }
}
