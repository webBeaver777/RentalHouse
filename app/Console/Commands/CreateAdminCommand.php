<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * M8 Phase 1: Create admin user command.
 *
 * Creates an admin user for Filament panel access.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin
                            {--email= : Email address for the admin}
                            {--name= : Display name for the admin}
                            {--password= : Password (will prompt if not provided)}
                            {--force : Overwrite if admin with this email exists}';

    protected $description = 'Create an admin user for Filament panel';

    public function handle(): int
    {
        $email = $this->option('email') ?? $this->ask('Enter admin email');
        $name = $this->option('name') ?? $this->ask('Enter admin name', 'Administrator');
        $password = $this->option('password') ?? $this->secret('Enter password (min 8 characters)');

        // Validate input
        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
        ], [
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'min:2'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        // Check if user exists
        $existingUser = User::where('email', $email)->first();

        if ($existingUser !== null) {
            if (! $this->option('force')) {
                $this->error("User with email {$email} already exists.");
                $this->info('Use --force to update existing user to admin.');

                return self::FAILURE;
            }

            // Update existing user to admin
            $existingUser->update([
                'name' => $name,
                'password' => Hash::make($password),
                'is_admin' => true,
            ]);

            $this->info("Updated existing user {$email} to admin.");

            return self::SUCCESS;
        }

        // Create new admin user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->info('Admin user created successfully!');
        $this->table(
            ['ID', 'Email', 'Name', 'Admin'],
            [[$user->id, $user->email, $user->name, 'Yes']]
        );

        $this->newLine();
        $this->info('Access Filament panel at: /admin');

        return self::SUCCESS;
    }
}
