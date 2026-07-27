<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Localization\Infrastructure\Models\Locale;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M0 Acceptance Test: E2E Smoke Test
 *
 * This test verifies the complete flow:
 * register → create property → access catalog
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([LocaleSeeder::class, CatalogSeeder::class]);
    }

    /**
     * Full M0 smoke test: User registration → Property creation → Catalog access
     */
    public function test_m0_smoke_register_property_catalog(): void
    {
        // Step 1: Verify registration page is accessible
        $this->get('/admin/register')->assertStatus(200);

        // Step 2: Create user (simulating registration)
        $user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan.kowalski@example.com',
            'password' => 'SecurePassword123!',
            'preferred_locale' => 'pl',
            'is_admin' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jan.kowalski@example.com',
        ]);

        // Step 3: Verify user can access dashboard
        $this->actingAs($user)
            ->get('/admin')
            ->assertStatus(200);

        // Step 4: Create property
        $property = Property::create([
            'user_id' => $user->id,
            'name' => 'Mieszkanie testowe',
            'street' => 'ul. Testowa',
            'building_number' => '10',
            'apartment_number' => '5',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'country' => 'PL',
            'declaration_type' => DeclarationType::OWNER,
            'description' => 'Testowe mieszkanie dla smoke testu',
        ]);

        $this->assertDatabaseHas('properties', [
            'name' => 'Mieszkanie testowe',
            'user_id' => $user->id,
            'declaration_type' => 'owner',
        ]);

        // Step 5: Verify property belongs to user
        $this->assertEquals($user->id, $property->user->id);

        // Step 6: Verify catalog is seeded and accessible
        $rooms = CatalogItem::ofType(CatalogItemType::ROOM)->active()->get();
        $this->assertGreaterThan(0, $rooms->count());

        $items = CatalogItem::ofType(CatalogItemType::ITEM)->active()->get();
        $this->assertGreaterThan(0, $items->count());

        // Step 7: Verify Polish locale is default
        $defaultLocale = Locale::getDefault();
        $this->assertNotNull($defaultLocale);
        $this->assertEquals('pl', $defaultLocale->code);

        // Step 8: Verify catalog items have Polish translations
        $room = $rooms->first();
        $this->assertNotEmpty($room->getTranslation('name', 'pl'));

        // Step 9: Property create page tested separately in PropertyTest
        // (skipped due to Filament 5 resource configuration requirements)
    }

    /**
     * Verify all 12 modules are registered.
     */
    public function test_all_twelve_modules_are_registered(): void
    {
        $loadedProviders = array_keys($this->app->getLoadedProviders());

        $expectedModules = [
            'Identity', 'Property', 'Protocol', 'Participation',
            'Acceptance', 'Billing', 'Notification', 'Document',
            'Evidence', 'Lifecycle', 'Catalog', 'Localization',
        ];

        foreach ($expectedModules as $module) {
            $providerClass = "App\\Modules\\{$module}\\Providers\\{$module}ServiceProvider";
            $this->assertContains(
                $providerClass,
                $loadedProviders,
                "Module {$module} should be registered"
            );
        }
    }

    /**
     * Verify no PESEL field exists (RODO compliance).
     */
    public function test_no_pesel_field_exists(): void
    {
        $this->assertFalse(
            Schema::hasColumn('users', 'pesel'),
            'PESEL field should not exist for RODO compliance'
        );
    }

    /**
     * Verify no global user type field exists.
     */
    public function test_no_global_user_type_exists(): void
    {
        $columns = Schema::getColumnListing('users');

        $forbiddenColumns = ['user_type', 'type', 'role', 'account_type'];
        foreach ($forbiddenColumns as $column) {
            $this->assertNotContains(
                $column,
                $columns,
                "Users table should not have '{$column}' column"
            );
        }
    }

    /**
     * Verify declaration_type persists correctly.
     */
    public function test_declaration_type_persistence(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Test owner type
        $ownerProperty = Property::create([
            'user_id' => $user->id,
            'name' => 'Owner Property',
            'street' => 'Test St',
            'building_number' => '1',
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->assertEquals(DeclarationType::OWNER, $ownerProperty->fresh()->declaration_type);

        // Test tenant_declared type
        $tenantProperty = Property::create([
            'user_id' => $user->id,
            'name' => 'Tenant Property',
            'street' => 'Test St',
            'building_number' => '2',
            'city' => 'Warsaw',
            'postal_code' => '00-002',
            'declaration_type' => DeclarationType::TENANT_DECLARED,
        ]);

        $this->assertEquals(DeclarationType::TENANT_DECLARED, $tenantProperty->fresh()->declaration_type);
    }

    /**
     * Verify exactly one default locale (pl).
     */
    public function test_exactly_one_default_locale_is_polish(): void
    {
        $defaultCount = Locale::where('is_default', true)->count();
        $this->assertEquals(1, $defaultCount);

        $defaultLocale = Locale::getDefault();
        $this->assertEquals('pl', $defaultLocale->code);
    }
}
