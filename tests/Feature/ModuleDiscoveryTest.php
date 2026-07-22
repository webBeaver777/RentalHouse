<?php

namespace Tests\Feature;

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
