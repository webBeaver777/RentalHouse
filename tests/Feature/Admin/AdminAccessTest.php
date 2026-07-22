<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Modules\Identity\Infrastructure\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * G6: Admin panel access control test.
 *
 * SECURITY: Regular users must NOT have access to /admin.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * G6: Regular user gets 403 on /admin.
     */
    public function test_regular_user_cannot_access_admin(): void
    {
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(403);
    }

    /**
     * G6: Admin user CAN access /admin.
     */
    public function test_admin_user_can_access_admin(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin');

        // Should either redirect to login or show dashboard (not 403)
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * G6: Unauthenticated user is redirected from /admin.
     */
    public function test_unauthenticated_user_redirected_from_admin(): void
    {
        $response = $this->get('/admin');

        // Should redirect to login, not give 403
        $response->assertRedirect();
    }

    /**
     * G6: User.canAccessPanel() returns correct values.
     */
    public function test_can_access_panel_returns_correct_value(): void
    {
        $regularUser = User::create([
            'name' => 'Regular',
            'email' => 'regular@example.com',
            'password' => 'password',
            'is_admin' => false,
        ]);

        $adminUser = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_admin' => true,
        ]);

        // Mock Panel for interface compliance
        $panel = $this->createMock(Panel::class);

        $this->assertFalse($regularUser->canAccessPanel($panel));
        $this->assertTrue($adminUser->canAccessPanel($panel));
    }
}
