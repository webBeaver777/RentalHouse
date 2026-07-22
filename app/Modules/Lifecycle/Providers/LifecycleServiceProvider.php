<?php

declare(strict_types=1);

namespace App\Modules\Lifecycle\Providers;

use App\Modules\Lifecycle\Application\Console\Commands\ProcessLifecycleCommand;
use App\Modules\Lifecycle\Application\Console\Commands\PurgeExpiredDataCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class LifecycleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Infrastructure/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessLifecycleCommand::class,
                PurgeExpiredDataCommand::class,
            ]);
        }

        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);

            // Process lifecycle every 15 minutes
            $schedule->command('lifecycle:process')
                ->everyFifteenMinutes()
                ->withoutOverlapping();

            // Purge expired data monthly
            $schedule->command('lifecycle:purge-expired')
                ->monthly()
                ->withoutOverlapping();
        });
    }
}
