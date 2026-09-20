<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * The account a person signs in with. Anyone signed in may change their own name, email and
 * password and nobody else's, and the two that are credentials need the current password.
 */
class ProfileTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function signedInOwner(string $password = 'old-password'): TenantUser
    {
        $owner = $this->makeOwner($this->makeTenant());
        $owner->update(['password' => $password, 'phone' => '0712345678']);
        $this->actingAs($owner, 'tenant');

        return $owner;
    }

    // ── Details ──────────────────────────────────────────────────────────────

    public function test_a_name_and_phone_change_needs_no_password(): void
    {
        $owner = $this->signedInOwner();

        $this->put('/profile', ['name' => 'Jagadi Juma', 'email' => $owner->email, 'phone' => '0799000111'])
            ->assertSessionHasNoErrors();

        $owner->refresh();
        $this->assertSame('Jagadi Juma', $owner->name);
        $this->assertSame('0799000111', $owner->phone);
    }

    public function test_the_page_opens_for_an_owner_and_for_an_agent(): void
    {
        $tenant = $this->makeTenant();
        $owner  = $this->makeOwner($tenant);
        $agent  = $this->makeAgent($tenant, 5000);

        $this->actingAs($owner, 'tenant')->get('/profile')->assertOk()->assertSee('Sign-in email');

        // Agents never reach /dashboard, so their own account must live outside it.
        $this->actingAs($agent, 'tenant')->get('/dashboard')->assertForbidden();
        $this->actingAs($agent, 'tenant')->get('/profile')->assertOk()->assertSee('Sign-in email');
    }

    public function test_a_signed_out_visitor_is_sent_to_sign_in(): void
    {
        $this->get('/profile')->assertRedirect(route('login'));
        $this->put('/profile', ['name' => 'X', 'email' => 'x@example.test'])->assertRedirect(route('login'));
    }

    // ── The sign-in email ────────────────────────────────────────────────────

    public function test_changing_the_sign_in_email_needs_the_current_password(): void
    {
        $owner = $this->signedInOwner();

        $this->put('/profile', ['name' => $owner->name, 'email' => 'new@example.test'])
            ->assertSessionHasErrors('current_password');

        $this->put('/profile', ['name' => $owner->name, 'email' => 'new@example.test', 'current_password' => 'not-it'])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('owner@example.test', $owner->fresh()->email, 'the email must not have moved');
    }

    public function test_the_new_email_is_the_one_that_signs_in_afterwards(): void
    {
        $owner = $this->signedInOwner();

        $this->put('/profile', [
            'name' => $owner->name, 'email' => 'new@example.test', 'current_password' => 'old-password',
        ])->assertSessionHasNoErrors();

        $this->post('/logout');

        $this->post('/login', ['email' => 'owner@example.test', 'password' => 'old-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('tenant');

        $this->post('/login', ['email' => 'new@example.test', 'password' => 'old-password']);
        $this->assertAuthenticated('tenant');
    }

    public function test_an_email_another_account_already_uses_is_refused(): void
    {
        $owner = $this->signedInOwner();
        $this->makeAgent($owner->tenant, 0, 'taken@example.test');

        $this->put('/profile', [
            'name' => $owner->name, 'email' => 'taken@example.test', 'current_password' => 'old-password',
        ])->assertSessionHasErrors('email');

        $this->assertSame('owner@example.test', $owner->fresh()->email);
    }

    public function test_keeping_your_own_email_is_not_a_change_and_needs_no_password(): void
    {
        $owner = $this->signedInOwner();

        $this->put('/profile', ['name' => 'Same Email', 'email' => strtoupper($owner->email)])
            ->assertSessionHasNoErrors();

        $this->assertSame('Same Email', $owner->fresh()->name);
    }

    // ── The password ─────────────────────────────────────────────────────────

    public function test_the_password_changes_only_with_the_current_one_and_a_matching_repeat(): void
    {
        $owner = $this->signedInOwner();

        $this->put('/profile/password', [
            'current_password' => 'wrong', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('current_password');

        $this->put('/profile/password', [
            'current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'mismatch',
        ])->assertSessionHasErrors('password');

        $this->put('/profile/password', [
            'current_password' => 'old-password', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password', $owner->fresh()->password), 'nothing above may have changed it');

        $this->put('/profile/password', [
            'current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $owner->fresh()->password));
    }

    public function test_the_new_password_is_the_one_that_signs_in_afterwards(): void
    {
        $owner = $this->signedInOwner();

        $this->put('/profile/password', [
            'current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasNoErrors();

        $this->post('/logout');

        $this->post('/login', ['email' => $owner->email, 'password' => 'old-password'])->assertSessionHasErrors('email');
        $this->assertGuest('tenant');

        $this->post('/login', ['email' => $owner->email, 'password' => 'brand-new-pass']);
        $this->assertAuthenticated('tenant');
    }

    public function test_a_new_password_drops_remember_me_on_other_devices(): void
    {
        $owner = $this->signedInOwner();
        $owner->forceFill(['remember_token' => 'token-from-another-device'])->save();

        $this->put('/profile/password', [
            'current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasNoErrors();

        $this->assertNotSame('token-from-another-device', $owner->fresh()->remember_token);
    }

    // ── Whose account it is ──────────────────────────────────────────────────

    public function test_the_form_cannot_name_a_different_account(): void
    {
        $owner = $this->signedInOwner();
        $other = $this->makeAgent($owner->tenant, 0, 'other@example.test');

        // Whoever is signed in is the account being changed. Nothing posted may redirect that.
        $this->put('/profile', [
            'id' => $other->id, 'user_id' => $other->id, 'tenant_user_id' => $other->id,
            'name' => 'Renamed', 'email' => $owner->email,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $owner->fresh()->name);
        $this->assertSame('Agent', $other->fresh()->name, 'the other account is untouched');
    }

    public function test_support_viewing_the_account_cannot_change_its_credentials(): void
    {
        $tenant = $this->makeTenant();
        $owner  = $this->makeOwner($tenant);
        $owner->update(['password' => 'old-password']);
        $this->actingAs($this->makeAdmin(), 'admin');
        $this->post("/admin/tenants/{$tenant->id}/impersonate");

        // Support may read the page, so they can help, but never save.
        $this->get('/profile')->assertOk()->assertSee('Not available in support view');

        $this->put('/profile', ['name' => 'Taken Over', 'email' => 'attacker@example.test', 'current_password' => 'old-password'])
            ->assertForbidden();
        $this->put('/profile/password', ['current_password' => 'old-password', 'password' => 'hijacked-pass', 'password_confirmation' => 'hijacked-pass'])
            ->assertForbidden();

        $owner->refresh();
        $this->assertSame('owner@example.test', $owner->email);
        $this->assertTrue(Hash::check('old-password', $owner->password));
        $this->assertSame(2, AuditLog::where('action', 'impersonation.blocked')->count());
    }

    // ── The trail ────────────────────────────────────────────────────────────

    public function test_credential_changes_and_wrong_guesses_are_recorded(): void
    {
        $owner = $this->signedInOwner();

        $this->put('/profile', ['name' => $owner->name, 'email' => 'new@example.test', 'current_password' => 'old-password']);
        $this->put('/profile/password', ['current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass']);
        $this->put('/profile/password', ['current_password' => 'wrong', 'password' => 'another-pass', 'password_confirmation' => 'another-pass']);

        $emailChange = AuditLog::where('action', 'profile.email_changed')->firstOrFail();
        $this->assertSame('owner@example.test', $emailChange->meta['from']);
        $this->assertSame('new@example.test', $emailChange->meta['to']);

        $this->assertSame(1, AuditLog::where('action', 'profile.password_changed')->count());
        $this->assertSame(1, AuditLog::where('action', 'profile.wrong_password')->count());

        // The password itself must never reach the trail.
        foreach (AuditLog::all() as $entry) {
            $this->assertStringNotContainsString('brand-new-pass', json_encode($entry->meta ?? []));
            $this->assertStringNotContainsString('old-password', json_encode($entry->meta ?? []));
        }
    }

    public function test_guessing_the_current_password_is_rate_limited(): void
    {
        $this->signedInOwner();

        for ($i = 0; $i < 6; $i++) {
            $this->put('/profile/password', [
                'current_password' => 'guess' . $i, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
            ])->assertSessionHasErrors('current_password');
        }

        $this->put('/profile/password', [
            'current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertStatus(429);
    }
}
