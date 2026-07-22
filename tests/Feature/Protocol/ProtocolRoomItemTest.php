<?php

namespace Tests\Feature\Protocol;

use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtocolRoomItemTest extends TestCase
{
    use RefreshDatabase;

    private Protocol $protocol;
    private CatalogItem $roomCatalog;
    private CatalogItem $itemCatalog;
    private CatalogItem $conditionCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $property = Property::create([
            'user_id' => $user->id,
            'name' => 'Test Property',
            'street' => 'Test Street',
            'building_number' => '1',
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->protocol = Protocol::create([
            'property_id' => $property->id,
            'created_by_user_id' => $user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Test Protocol',
        ]);

        $this->roomCatalog = CatalogItem::ofType(CatalogItemType::ROOM)->first();
        $this->itemCatalog = CatalogItem::ofType(CatalogItemType::ITEM)->first();
        $this->conditionCatalog = CatalogItem::ofType(CatalogItemType::CONDITION)->first();
    }

    /**
     * Protocol room can be created.
     */
    public function test_protocol_room_can_be_created(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $this->assertDatabaseHas('protocol_rooms', [
            'id' => $room->id,
            'protocol_id' => $this->protocol->id,
        ]);
    }

    /**
     * Protocol room has UUID.
     */
    public function test_protocol_room_has_uuid(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $room->id
        );
    }

    /**
     * Protocol room belongs to protocol.
     */
    public function test_protocol_room_belongs_to_protocol(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $this->assertEquals($this->protocol->id, $room->protocol->id);
    }

    /**
     * Protocol room belongs to catalog item.
     */
    public function test_protocol_room_belongs_to_catalog_item(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $this->assertEquals($this->roomCatalog->id, $room->catalogItem->id);
    }

    /**
     * Protocol room can have custom name.
     */
    public function test_protocol_room_can_have_custom_name(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
            'custom_name' => 'Mój salon',
        ]);

        $this->assertEquals('Mój salon', $room->display_name);
    }

    /**
     * Protocol room display name falls back to catalog.
     */
    public function test_protocol_room_display_name_falls_back_to_catalog(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $this->assertNotEmpty($room->display_name);
    }

    /**
     * Protocol has rooms relationship.
     */
    public function test_protocol_has_rooms(): void
    {
        ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
            'sort_order' => 1,
        ]);

        ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
            'custom_name' => 'Second Room',
            'sort_order' => 2,
        ]);

        $this->assertEquals(2, $this->protocol->rooms()->count());
    }

    /**
     * Protocol item can be created.
     */
    public function test_protocol_item_can_be_created(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $item = ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $this->itemCatalog->id,
            'condition_catalog_item_id' => $this->conditionCatalog->id,
        ]);

        $this->assertDatabaseHas('protocol_items', [
            'id' => $item->id,
            'protocol_room_id' => $room->id,
        ]);
    }

    /**
     * Protocol item has UUID.
     */
    public function test_protocol_item_has_uuid(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $item = ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $this->itemCatalog->id,
        ]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $item->id
        );
    }

    /**
     * Protocol item belongs to room.
     */
    public function test_protocol_item_belongs_to_room(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $item = ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $this->itemCatalog->id,
        ]);

        $this->assertEquals($room->id, $item->room->id);
    }

    /**
     * Protocol item has condition.
     */
    public function test_protocol_item_has_condition(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $item = ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $this->itemCatalog->id,
            'condition_catalog_item_id' => $this->conditionCatalog->id,
        ]);

        $this->assertEquals($this->conditionCatalog->id, $item->condition->id);
    }

    /**
     * Protocol item has condition name attribute.
     */
    public function test_protocol_item_has_condition_name(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $item = ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $this->itemCatalog->id,
            'condition_catalog_item_id' => $this->conditionCatalog->id,
        ]);

        $this->assertNotEmpty($item->condition_name);
    }

    /**
     * Protocol item defaults to quantity 1.
     */
    public function test_protocol_item_defaults_to_quantity_one(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $item = ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $this->itemCatalog->id,
        ]);

        $this->assertEquals(1, $item->quantity);
    }

    /**
     * Protocol item can store defects as JSON.
     */
    public function test_protocol_item_can_store_defects(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $defects = [
            ['catalog_item_id' => 1, 'notes' => 'Zarysowanie na lewej stronie'],
            ['catalog_item_id' => 2, 'notes' => 'Pęknięcie w rogu'],
        ];

        $item = ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $this->itemCatalog->id,
            'defects' => $defects,
        ]);

        $this->assertEquals($defects, $item->fresh()->defects);
        $this->assertTrue($item->hasDefects());
    }

    /**
     * Protocol item without defects returns false for hasDefects.
     */
    public function test_protocol_item_without_defects(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $item = ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $this->itemCatalog->id,
        ]);

        $this->assertFalse($item->hasDefects());
    }

    /**
     * Room has items relationship.
     */
    public function test_room_has_items(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $this->itemCatalog->id,
            'sort_order' => 1,
        ]);

        ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $this->itemCatalog->id,
            'sort_order' => 2,
        ]);

        $this->assertEquals(2, $room->items()->count());
    }

    /**
     * Protocol room can be soft deleted.
     */
    public function test_protocol_room_can_be_soft_deleted(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $roomId = $room->id;
        $room->delete();

        $this->assertSoftDeleted('protocol_rooms', ['id' => $roomId]);
    }

    /**
     * Protocol item can be soft deleted.
     */
    public function test_protocol_item_can_be_soft_deleted(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $this->roomCatalog->id,
        ]);

        $item = ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $this->itemCatalog->id,
        ]);

        $itemId = $item->id;
        $item->delete();

        $this->assertSoftDeleted('protocol_items', ['id' => $itemId]);
    }
}
