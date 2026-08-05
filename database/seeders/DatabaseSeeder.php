<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            LocaleSeeder::class,
            CatalogSeeder::class,
        ]);

        // M8 Phase 1: only seed an admin from .env if both vars are set.
        // AdminSeeder itself also no-ops (with a warning) if they're missing,
        // but gating here keeps the intent explicit at the call site.
        if (config('admin.email') && config('admin.password')) {
            $this->call(AdminSeeder::class);
        }

        // Create test user (non-admin)
        if (! User::where('email', 'test@example.com')->exists()) {
            User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'is_admin' => false,
            ]);
        }
    }
}
