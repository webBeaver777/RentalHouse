<?php

namespace App\Modules\Participation\Providers;

use Illuminate\Support\ServiceProvider;

class ParticipationServiceProvider extends ServiceProvider
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
