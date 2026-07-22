<?php

namespace App\Modules\Evidence\Providers;

use Illuminate\Support\ServiceProvider;

class EvidenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Infrastructure/Migrations');
    }
}
