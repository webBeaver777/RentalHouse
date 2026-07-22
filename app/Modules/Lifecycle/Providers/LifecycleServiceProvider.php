<?php

namespace App\Modules\Lifecycle\Providers;

use Illuminate\Support\ServiceProvider;

class LifecycleServiceProvider extends ServiceProvider
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
