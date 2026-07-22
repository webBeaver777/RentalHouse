<?php

declare(strict_types=1);

namespace App\Modules\Acceptance\Providers;

use Illuminate\Support\ServiceProvider;

class AcceptanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Infrastructure/Migrations');
    }
}
