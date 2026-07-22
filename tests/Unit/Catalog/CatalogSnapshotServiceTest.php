<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use App\Modules\Catalog\Application\Services\CatalogSnapshotService;
use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Localization\Infrastructure\Models\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    private CatalogSnapshotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CatalogSnapshotService;

        // Create default locale
        Locale::create([
            'code' => 'pl',
            'name' => 'Polish',
            'native_name' => 'Polski',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Locale::create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    public function test_snapshot_name_captures_all_translations(): void
    {
        $item = CatalogItem::create([
            'type' => CatalogItemType::ROOM,
            'name' => ['pl' => 'Salon', 'en' => 'Living room'],
            'is_active' => true,
        ]);

        $snapshot = $this->service->snapshotName($item);

        $this->assertArrayHasKey('pl', $snapshot);
        $this->assertArrayHasKey('en', $snapshot);
        $this->assertEquals('Salon', $snapshot['pl']);
        $this->assertEquals('Living room', $snapshot['en']);
    }

    public function test_snapshot_is_immutable_after_catalog_change(): void
    {
        $item = CatalogItem::create([
            'type' => CatalogItemType::ROOM,
            'name' => ['pl' => 'Salon', 'en' => 'Living room'],
            'is_active' => true,
        ]);

        // Take snapshot
        $snapshot = $this->service->snapshotName($item);

        // Change the catalog item
        $item->update(['name' => ['pl' => 'Pokój dzienny', 'en' => 'Lounge']]);

        // Snapshot should still have old values
        $this->assertEquals('Salon', $snapshot['pl']);
        $this->assertEquals('Living room', $snapshot['en']);

        // Fresh item has new values
        $freshItem = $item->fresh();
        $this->assertEquals('Pokój dzienny', $freshItem->getTranslation('name', 'pl'));
    }

    public function test_get_name_from_snapshot_returns_correct_locale(): void
    {
        $snapshot = ['pl' => 'Kuchnia', 'en' => 'Kitchen'];

        $this->assertEquals('Kitchen', $this->service->getNameFromSnapshot($snapshot, 'en'));
        $this->assertEquals('Kuchnia', $this->service->getNameFromSnapshot($snapshot, 'pl'));
    }

    public function test_get_name_from_snapshot_falls_back_to_polish(): void
    {
        $snapshot = ['pl' => 'Łazienka'];

        // Request English but only Polish exists
        $this->assertEquals('Łazienka', $this->service->getNameFromSnapshot($snapshot, 'en'));
    }

    public function test_snapshot_many_captures_multiple_items(): void
    {
        CatalogItem::create([
            'type' => CatalogItemType::ROOM,
            'name' => ['pl' => 'Salon'],
            'is_active' => true,
        ]);

        CatalogItem::create([
            'type' => CatalogItemType::ROOM,
            'name' => ['pl' => 'Kuchnia'],
            'is_active' => true,
        ]);

        $items = CatalogItem::all();
        $snapshots = $this->service->snapshotMany($items);

        $this->assertCount(2, $snapshots);
        $this->assertEquals('room', $snapshots[0]['type']);
        $this->assertArrayHasKey('name_snapshot', $snapshots[0]);
    }

    public function test_snapshot_full_includes_description(): void
    {
        $item = CatalogItem::create([
            'type' => CatalogItemType::ITEM,
            'name' => ['pl' => 'Lodówka', 'en' => 'Refrigerator'],
            'description' => ['pl' => 'Duża lodówka', 'en' => 'Large refrigerator'],
            'is_active' => true,
        ]);

        $snapshot = $this->service->snapshotFull($item);

        $this->assertArrayHasKey('name', $snapshot);
        $this->assertArrayHasKey('description', $snapshot);
        $this->assertEquals('Lodówka', $snapshot['name']['pl']);
        $this->assertEquals('Duża lodówka', $snapshot['description']['pl']);
    }
}
