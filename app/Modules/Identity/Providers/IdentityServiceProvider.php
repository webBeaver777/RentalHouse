<?php

namespace App\Modules\Identity\Providers;

use App\Modules\Identity\Application\Actions\RegisterUserAction;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RegisterUserAction::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Infrastructure/Migrations');
    }
}
