<?php

namespace App\Modules\Protocol\Providers;

use Illuminate\Support\ServiceProvider;

class ProtocolServiceProvider extends ServiceProvider
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
