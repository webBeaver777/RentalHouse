<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Acceptance\Providers\AcceptanceServiceProvider;
use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Document\Providers\DocumentServiceProvider;
use App\Modules\Evidence\Providers\EvidenceServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Lifecycle\Providers\LifecycleServiceProvider;
use App\Modules\Localization\Providers\LocalizationServiceProvider;
use App\Modules\Notification\Providers\NotificationServiceProvider;
use App\Modules\Participation\Providers\ParticipationServiceProvider;
use App\Modules\Property\Providers\PropertyServiceProvider;
use App\Modules\Protocol\Providers\ProtocolServiceProvider;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * All module service providers.
     */
    protected array $moduleProviders = [
        IdentityServiceProvider::class,
        PropertyServiceProvider::class,
        ProtocolServiceProvider::class,
        ParticipationServiceProvider::class,
        AcceptanceServiceProvider::class,
        BillingServiceProvider::class,
        NotificationServiceProvider::class,
        DocumentServiceProvider::class,
        EvidenceServiceProvider::class,
        LifecycleServiceProvider::class,
        CatalogServiceProvider::class,
        LocalizationServiceProvider::class,
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
