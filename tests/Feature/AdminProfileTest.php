<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * The platform admin's own account: name, sign-in email, phone and password. The mirror of
 * ProfileTest, for the one account that runs the platform rather than a tenant.
 */
class AdminProfileTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function signedInAdmin(string $password = 'old-password'): PlatformAdmin
    {
        $admin = $this->makeAdmin();
        $admin->update(['password' => $password]);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_a_name_and_phone_change_needs_no_password(): void
    {
        $admin = $this->signedInAdmin();

        $this->put('/admin/profile', ['name' => 'Jagadi Juma', 'email' => $admin->email, 'phone' => '0799000111'])
            ->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertSame('Jagadi Juma', $admin->name);
        $this->assertSame('0799000111', $admin->phone);
    }

    public function test_the_page_opens_and_shows_the_signed_in_admins_own_details(): void
    {
        $admin = $this->signedInAdmin();

        $this->get('/admin/profile')->assertOk()->assertSee('Sign-in email')->assertSee($admin->email);
    }

    public function test_a_signed_out_visitor_is_sent_to_the_admin_sign_in(): void
    {
        $this->get('/admin/profile')->assertRedirect(route('admin.login'));
        $this->put('/admin/profile', ['name' => 'X', 'email' => 'x@example.test'])->assertRedirect(route('admin.login'));
    }

    // ── The sign-in email ────────────────────────────────────────────────────

    public function test_changing_the_sign_in_email_needs_the_current_password(): void
    {
        $admin = $this->signedInAdmin();

        $this->put('/admin/profile', ['name' => $admin->name, 'email' => 'new@example.test'])
            ->assertSessionHasErrors('current_password');

        $this->put('/admin/profile', ['name' => $admin->name, 'email' => 'new@example.test', 'current_password' => 'not-it'])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('admin@example.test', $admin->fresh()->email, 'the email must not have moved');
    }

    public function test_the_new_email_is_the_one_that_signs_in_afterwards(): void
    {
        $admin = $this->signedInAdmin();

        $this->put('/admin/profile', [
            'name' => $admin->name, 'email' => 'new@example.test', 'current_password' => 'old-password',
        ])->assertSessionHasNoErrors();

        $this->post('/admin/logout');

        $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'old-password']);
        $this->assertGuest('admin');

        $this->post('/admin/login', ['email' => 'new@example.test', 'password' => 'old-password']);
        $this->assertAuthenticated('admin');
    }

    public function test_an_email_another_admin_already_uses_is_refused(): void
    {
        $admin = $this->signedInAdmin();
        PlatformAdmin::create(['name' => 'Other', 'email' => 'taken@example.test', 'password' => 'password']);

        $this->put('/admin/profile', [
            'name' => $admin->name, 'email' => 'taken@example.test', 'current_password' => 'old-password',
        ])->assertSessionHasErrors('email');

        $this->assertSame('admin@example.test', $admin->fresh()->email);
    }

    // ── The password ─────────────────────────────────────────────────────────

    public function test_the_password_changes_only_with_the_current_one_and_a_matching_repeat(): void
    {
        $admin = $this->signedInAdmin();

        $this->put('/admin/profile/password', [
            'current_password' => 'wrong', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('current_password');

        $this->put('/admin/profile/password', [
            'current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'mismatch',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password', $admin->fresh()->password), 'nothing above may have changed it');

        $this->put('/admin/profile/password', [
            'current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $admin->fresh()->password));
    }

    public function test_a_new_password_drops_remember_me_on_other_devices(): void
    {
        $admin = $this->signedInAdmin();
        $admin->forceFill(['remember_token' => 'token-from-another-device'])->save();

        $this->put('/admin/profile/password', [
            'current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasNoErrors();

        $this->assertNotSame('token-from-another-device', $admin->fresh()->remember_token);
    }

    // ── The trail ────────────────────────────────────────────────────────────

    public function test_credential_changes_and_wrong_guesses_are_recorded(): void
    {
        $admin = $this->signedInAdmin();

        $this->put('/admin/profile', ['name' => $admin->name, 'email' => 'new@example.test', 'current_password' => 'old-password']);
        $this->put('/admin/profile/password', ['current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass']);
        $this->put('/admin/profile/password', ['current_password' => 'wrong', 'password' => 'another-pass', 'password_confirmation' => 'another-pass']);

        $emailChange = AuditLog::where('action', 'profile.admin_email_changed')->firstOrFail();
        $this->assertSame('admin@example.test', $emailChange->meta['from']);
        $this->assertSame('new@example.test', $emailChange->meta['to']);

        $this->assertSame(1, AuditLog::where('action', 'profile.admin_password_changed')->count());
        $this->assertSame(1, AuditLog::where('action', 'profile.admin_wrong_password')->count());

        foreach (AuditLog::all() as $entry) {
            $this->assertStringNotContainsString('brand-new-pass', json_encode($entry->meta ?? []));
            $this->assertStringNotContainsString('old-password', json_encode($entry->meta ?? []));
        }
    }

    public function test_guessing_the_current_password_is_rate_limited(): void
    {
        $this->signedInAdmin();

        for ($i = 0; $i < 6; $i++) {
            $this->put('/admin/profile/password', [
                'current_password' => 'guess' . $i, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
            ])->assertSessionHasErrors('current_password');
        }

        $this->put('/admin/profile/password', [
            'current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertStatus(429);
    }

    // ── Who the form may change ─────────────────────────────────────────────

    public function test_a_signed_in_tenant_owner_cannot_reach_the_admin_profile(): void
    {
        $owner = $this->makeOwner($this->makeTenant());
        $this->actingAs($owner, 'tenant');

        $this->get('/admin/profile')->assertRedirect(route('admin.login'));
    }
}
