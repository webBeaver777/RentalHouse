<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Registration page is accessible.
     */
    public function test_registration_page_is_accessible(): void
    {
        $response = $this->get('/admin/register');

        $response->assertStatus(200);
        $response->assertSee('Zarejestruj');
    }

    /**
     * Login page is accessible.
     */
    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
    }

    /**
     * Authenticated user can access admin dashboard.
     */
    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
            'is_admin' => true,
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(200);
    }

    /**
     * Guest cannot access admin dashboard.
     */
    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    /**
     * User can be created programmatically (simulates registration).
     */
    public function test_user_registration_creates_user(): void
    {
        $user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jan@example.com',
            'name' => 'Jan Kowalski',
        ]);

        $this->assertNotNull($user->id);
    }

    /**
     * Password is hashed when creating user.
     */
    public function test_password_is_hashed(): void
    {
        $user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
        ]);

        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(password_verify('password123', $user->password));
    }
}
