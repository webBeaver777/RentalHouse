<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    /**
     * Catalog items are seeded with Polish translations.
     */
    public function test_catalog_items_have_polish_translations(): void
    {
        $room = CatalogItem::ofType(CatalogItemType::ROOM)->first();

        $this->assertNotNull($room);
        $this->assertNotNull($room->getTranslation('name', 'pl'));
    }

    /**
     * Catalog items have translatable name.
     */
    public function test_catalog_item_name_is_translatable(): void
    {
        $item = CatalogItem::create([
            'type' => CatalogItemType::ITEM,
            'name' => [
                'pl' => 'Telewizor',
                'en' => 'Television',
            ],
            'is_active' => true,
        ]);

        $this->assertEquals('Telewizor', $item->getTranslation('name', 'pl'));
        $this->assertEquals('Television', $item->getTranslation('name', 'en'));
    }

    /**
     * Room types are seeded.
     */
    public function test_room_types_are_seeded(): void
    {
        $rooms = CatalogItem::ofType(CatalogItemType::ROOM)->get();

        $this->assertGreaterThan(0, $rooms->count());

        // Check some Polish room names exist
        $roomNames = $rooms->map(fn($r) => $r->getTranslation('name', 'pl'))->toArray();
        $this->assertContains('Salon', $roomNames);
        $this->assertContains('Kuchnia', $roomNames);
        $this->assertContains('Łazienka', $roomNames);
    }

    /**
     * Item templates are seeded.
     */
    public function test_item_templates_are_seeded(): void
    {
        $items = CatalogItem::ofType(CatalogItemType::ITEM)->get();

        $this->assertGreaterThan(0, $items->count());

        // Check some Polish item names exist
        $itemNames = $items->map(fn($i) => $i->getTranslation('name', 'pl'))->toArray();
        $this->assertContains('Ściany', $itemNames);
        $this->assertContains('Podłoga', $itemNames);
    }

    /**
     * Condition states are seeded.
     */
    public function test_condition_states_are_seeded(): void
    {
        $conditions = CatalogItem::ofType(CatalogItemType::CONDITION)->get();

        $this->assertGreaterThan(0, $conditions->count());

        $names = $conditions->map(fn($c) => $c->getTranslation('name', 'pl'))->toArray();
        $this->assertContains('Nowy', $names);
        $this->assertContains('Dobry', $names);
        $this->assertContains('Uszkodzony', $names);
    }

    /**
     * Defect types are seeded.
     */
    public function test_defect_types_are_seeded(): void
    {
        $defects = CatalogItem::ofType(CatalogItemType::DEFECT)->get();

        $this->assertGreaterThan(0, $defects->count());

        $names = $defects->map(fn($d) => $d->getTranslation('name', 'pl'))->toArray();
        $this->assertContains('Zarysowanie', $names);
        $this->assertContains('Pęknięcie', $names);
    }

    /**
     * Catalog item can be soft deleted.
     */
    public function test_catalog_item_can_be_soft_deleted(): void
    {
        $item = CatalogItem::ofType(CatalogItemType::ROOM)->first();
        $itemId = $item->id;

        $item->delete();

        $this->assertSoftDeleted('catalog_items', ['id' => $itemId]);
    }

    /**
     * Active scope filters correctly.
     */
    public function test_active_scope_works(): void
    {
        $item = CatalogItem::first();
        $item->update(['is_active' => false]);

        $activeItems = CatalogItem::active()->get();

        $this->assertFalse($activeItems->contains('id', $item->id));
    }
}
