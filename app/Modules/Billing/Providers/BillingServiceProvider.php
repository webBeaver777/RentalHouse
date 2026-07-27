<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Application\Services\FakePaymentGateway;
use App\Modules\Billing\Application\Services\Przelewy24Service;
use App\Modules\Billing\Domain\Contracts\PaymentGatewayInterface;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind PaymentGateway based on environment
        $this->app->singleton(PaymentGatewayInterface::class, function ($app) {
            // Use FakeGateway in testing environment
            if ($app->environment('testing')) {
                return new FakePaymentGateway;
            }

            // Use real Przelewy24 in production/local
            return new Przelewy24Service;
        });

        // Also register concrete classes as singletons
        $this->app->singleton(Przelewy24Service::class);
        $this->app->singleton(FakePaymentGateway::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Infrastructure/Migrations');
    }
}
