<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * M8 Phase 1: Admin user seeder.
 *
 * Reads ADMIN_EMAIL / ADMIN_PASSWORD from .env and idempotently
 * creates or promotes that user to is_admin=true. No-op (with a
 * console warning) if either variable is unset — this seeder is
 * NOT meant to ship a hardcoded default credential.
 *
 * Call site: DatabaseSeeder must only invoke this when both env
 * vars are present (see DatabaseSeeder::run()).
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set in .env — skipping AdminSeeder.');

            return;
        }

        $user = User::where('email', $email)->first();

        if ($user !== null) {
            $user->update([
                'password' => Hash::make($password),
                'is_admin' => true,
            ]);

            $this->command?->info("Existing user {$email} promoted to admin.");

            return;
        }

        User::create([
            'name' => 'Administrator',
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->command?->info("Admin user created: {$email}");
    }
}
