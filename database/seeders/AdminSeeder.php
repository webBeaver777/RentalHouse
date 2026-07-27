<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * M8 Phase 1: Admin user seeder for development.
 *
 * Creates a default admin user for local development.
 * Credentials: admin@rent2proof.local / admin123
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@rent2proof.local';

        // Skip if admin already exists
        if (User::where('email', $email)->exists()) {
            $this->command->info("Admin user {$email} already exists, skipping.");

            return;
        }

        User::create([
            'name' => 'Administrator',
            'email' => $email,
            'password' => Hash::make('admin123'),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->command->info("Admin user created: {$email} / admin123");
    }
}
