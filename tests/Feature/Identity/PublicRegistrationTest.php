<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * M8 Phase 2: Public registration/login for regular (non-admin) users.
 *
 * Not to be confused with tests/Feature/Identity/RegistrationTest.php,
 * which despite its name tests Filament's own /admin/register panel auth
 * (AdminPanelProvider::registration()) — a different, admin-only surface.
 * This covers the actual public flow: § "зарегистрировался → попал в
 * кабинет", no PESEL, no global landlord/tenant role.
 *
 * No prior test in this suite POSTs through real 'web' routes (everything
 * else drives Action classes directly), so this is the first to hit CSRF
 * (Illuminate\Foundation\Http\Middleware\PreventRequestForgery) — disabled
 * here only, not globally, so auth/guest middleware is still exercised.
 */
class PublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_register_page_renders(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_user_can_register_and_lands_in_dashboard(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::where('email', 'jan@example.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check('secret1234', $user->password));

        // §21 canon: no PESEL, no global role field on the account.
        $this->assertArrayNotHasKey('pesel', $user->getAttributes());
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->post('/register', [
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::create([
            'name' => 'Existing',
            'email' => 'jan@example.com',
            'password' => 'password',
        ]);

        $this->post('/register', [
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertSessionHasErrors('email');
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'secret1234',
        ]);

        $response = $this->post('/login', [
            'email' => 'jan@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'secret1234',
        ]);

        $this->post('/login', [
            'email' => 'jan@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'secret1234',
        ]);

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'secret1234',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard'));
    }
}
