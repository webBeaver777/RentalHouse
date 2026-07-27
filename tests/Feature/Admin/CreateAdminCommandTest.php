<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Modules\Identity\Infrastructure\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests for app:create-admin command.
 */
class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test command creates admin user with provided options.
     */
    public function test_creates_admin_user_with_options(): void
    {
        $this->artisan('app:create-admin', [
            '--email' => 'new-admin@test.com',
            '--name' => 'New Admin',
            '--password' => 'securepassword123',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Admin user created successfully');

        $user = User::where('email', 'new-admin@test.com')->first();

        $this->assertNotNull($user);
        $this->assertEquals('New Admin', $user->name);
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('securepassword123', $user->password));
    }

    /**
     * Test command fails with invalid email.
     */
    public function test_fails_with_invalid_email(): void
    {
        $this->artisan('app:create-admin', [
            '--email' => 'not-an-email',
            '--name' => 'Admin',
            '--password' => 'password123',
        ])
            ->assertFailed();
    }

    /**
     * Test command fails with short password.
     */
    public function test_fails_with_short_password(): void
    {
        $this->artisan('app:create-admin', [
            '--email' => 'admin@test.com',
            '--name' => 'Admin',
            '--password' => 'short',
        ])
            ->assertFailed();
    }

    /**
     * Test command fails if email already exists without force.
     */
    public function test_fails_if_email_exists_without_force(): void
    {
        User::create([
            'name' => 'Existing User',
            'email' => 'existing@test.com',
            'password' => 'password',
            'is_admin' => false,
        ]);

        $this->artisan('app:create-admin', [
            '--email' => 'existing@test.com',
            '--name' => 'Admin',
            '--password' => 'password123',
        ])
            ->assertFailed()
            ->expectsOutputToContain('already exists');
    }

    /**
     * Test command updates existing user to admin with force.
     */
    public function test_updates_existing_user_to_admin_with_force(): void
    {
        User::create([
            'name' => 'Existing User',
            'email' => 'existing@test.com',
            'password' => 'oldpassword',
            'is_admin' => false,
        ]);

        $this->artisan('app:create-admin', [
            '--email' => 'existing@test.com',
            '--name' => 'Updated Admin',
            '--password' => 'newpassword123',
            '--force' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Updated existing user');

        $user = User::where('email', 'existing@test.com')->first();

        $this->assertEquals('Updated Admin', $user->name);
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    /**
     * Test AdminSeeder creates default admin.
     */
    public function test_admin_seeder_creates_default_admin(): void
    {
        $this->seed(AdminSeeder::class);

        $user = User::where('email', 'admin@rent2proof.local')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin);
        $this->assertEquals('Administrator', $user->name);
    }

    /**
     * Test AdminSeeder skips if admin exists.
     */
    public function test_admin_seeder_skips_if_exists(): void
    {
        User::create([
            'name' => 'Existing Admin',
            'email' => 'admin@rent2proof.local',
            'password' => 'password',
            'is_admin' => true,
        ]);

        // Should not throw
        $this->seed(AdminSeeder::class);

        // Only one admin with this email
        $count = User::where('email', 'admin@rent2proof.local')->count();
        $this->assertEquals(1, $count);
    }
}
