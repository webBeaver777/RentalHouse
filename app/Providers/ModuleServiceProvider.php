<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * All module service providers.
     */
    protected array $moduleProviders = [
        \App\Modules\Identity\Providers\IdentityServiceProvider::class,
        \App\Modules\Property\Providers\PropertyServiceProvider::class,
        \App\Modules\Protocol\Providers\ProtocolServiceProvider::class,
        \App\Modules\Participation\Providers\ParticipationServiceProvider::class,
        \App\Modules\Acceptance\Providers\AcceptanceServiceProvider::class,
        \App\Modules\Billing\Providers\BillingServiceProvider::class,
        \App\Modules\Notification\Providers\NotificationServiceProvider::class,
        \App\Modules\Document\Providers\DocumentServiceProvider::class,
        \App\Modules\Evidence\Providers\EvidenceServiceProvider::class,
        \App\Modules\Lifecycle\Providers\LifecycleServiceProvider::class,
        \App\Modules\Catalog\Providers\CatalogServiceProvider::class,
        \App\Modules\Localization\Providers\LocalizationServiceProvider::class,
    ];

    public function register(): void
    {
        foreach ($this->moduleProviders as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        //
    }

    /**
     * Get all registered module providers.
     */
    public static function getModuleProviders(): array
    {
        return (new self(app()))->moduleProviders;
    }
}
