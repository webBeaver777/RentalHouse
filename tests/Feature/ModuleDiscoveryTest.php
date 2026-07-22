<?php

declare(strict_types=1);

namespace Tests\Feature;

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
use App\Providers\ModuleServiceProvider;
use Tests\TestCase;

class ModuleDiscoveryTest extends TestCase
{
    /**
     * All 12 bounded contexts should be registered.
     */
    public function test_all_twelve_module_providers_are_registered(): void
    {
        $loadedProviders = array_keys($this->app->getLoadedProviders());

        $expectedProviders = [
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

        foreach ($expectedProviders as $provider) {
            $this->assertContains(
                $provider,
                $loadedProviders,
                "Module provider {$provider} should be registered"
            );
        }

        $this->assertCount(12, $expectedProviders);
    }

    /**
     * ModuleServiceProvider should return exactly 12 module providers.
     */
    public function test_module_service_provider_has_twelve_modules(): void
    {
        $providers = ModuleServiceProvider::getModuleProviders();

        $this->assertCount(12, $providers);
    }
}
