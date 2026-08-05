<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\CatalogItemResource;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M8-VERIFY: regression test for the Filament 5 migration bug found in
 * PropertyResource — form input components (TextInput/Select/Textarea/
 * Toggle) imported from Filament\Schemas\Components instead of
 * Filament\Forms\Components crash with "Class ... not found" on
 * page mount. CatalogItemResource had the exact same bug and, unlike
 * Property, had no HTTP-level test at all, so it would have shipped
 * broken silently.
 */
class CatalogItemResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
    }

    public function test_authenticated_admin_can_access_catalog_items_list(): void
    {
        $response = $this->actingAs($this->admin)->get(CatalogItemResource::getUrl('index'));

        $response->assertOk();
    }

    public function test_authenticated_admin_can_access_create_catalog_item(): void
    {
        $response = $this->actingAs($this->admin)->get(CatalogItemResource::getUrl('create'));

        $response->assertOk();
    }
}
