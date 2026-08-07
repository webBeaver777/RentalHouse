<?php

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M10.2, Scenario A slice: rooms + items CRUD HTTP layer on a draft
 * check-in protocol, including the KRYTYCZNY GUARD — mutation must be
 * rejected on a non-draft protocol and for a non-initiator, at the
 * controller level (not just hidden in the UI).
 *
 * Same convention as ProtocolHttpTest: CSRF disabled here only.
 */
class ProtocolRoomItemHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Property $property;

    private Protocol $draftProtocol;

    private CatalogItem $roomType;

    private CatalogItem $itemTemplate;

    private CatalogItem $conditionState;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->seed(CatalogSeeder::class);

        $this->user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
        ]);

        $this->property = Property::create([
            'user_id' => $this->user->id,
            'name' => 'Mieszkanie Testowe',
            'street' => 'ul. Testowa',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->draftProtocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Protokół wjazdu',
        ]);

        $this->roomType = CatalogItem::ofType(CatalogItemType::ROOM)->first();
        $this->itemTemplate = CatalogItem::ofType(CatalogItemType::ITEM)->first();
        $this->conditionState = CatalogItem::ofType(CatalogItemType::CONDITION)->first();
    }

    private function otherUser(): User
    {
        return User::create([
            'name' => 'Anna Nowak',
            'email' => 'anna@example.com',
            'password' => 'password123',
        ]);
    }

    /* --- rooms CRUD on draft --- */

    public function test_initiator_can_add_room_to_draft_protocol(): void
    {
        $response = $this->actingAs($this->user)->post(
            route('protocols.rooms.store', $this->draftProtocol),
            ['catalog_item_id' => $this->roomType->id]
        );

        $response->assertRedirect(route('protocols.show', $this->draftProtocol));
        $this->assertDatabaseHas('protocol_rooms', [
            'protocol_id' => $this->draftProtocol->id,
            'catalog_item_id' => $this->roomType->id,
        ]);
    }

    public function test_initiator_can_rename_room(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->draftProtocol->id,
            'catalog_item_id' => $this->roomType->id,
        ]);

        $response = $this->actingAs($this->user)->put(
            route('protocols.rooms.update', [$this->draftProtocol, $room]),
            ['custom_name' => 'Mój salon']
        );

        $response->assertRedirect(route('protocols.show', $this->draftProtocol));
        $this->assertDatabaseHas('protocol_rooms', [
            'id' => $room->id,
            'custom_name' => 'Mój salon',
        ]);
    }

    public function test_initiator_can_delete_room(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->draftProtocol->id,
            'catalog_item_id' => $this->roomType->id,
        ]);

        $response = $this->actingAs($this->user)->delete(
            route('protocols.rooms.destroy', [$this->draftProtocol, $room])
        );

        $response->assertRedirect(route('protocols.show', $this->draftProtocol));
        $this->assertSoftDeleted('protocol_rooms', ['id' => $room->id]);
    }

    /* --- items CRUD on draft --- */

    public function test_initiator_can_add_item_to_room(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->draftProtocol->id,
            'catalog_item_id' => $this->roomType->id,
        ]);

        $response = $this->actingAs($this->user)->post(
            route('protocols.rooms.items.store', [$this->draftProtocol, $room]),
            [
                'catalog_item_id' => $this->itemTemplate->id,
                'condition_catalog_item_id' => $this->conditionState->id,
                'quantity' => 2,
            ]
        );

        $response->assertRedirect(route('protocols.show', $this->draftProtocol));
        $this->assertDatabaseHas('protocol_items', [
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $this->itemTemplate->id,
            'condition_catalog_item_id' => $this->conditionState->id,
            'quantity' => 2,
        ]);
    }

    public function test_initiator_can_edit_item(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->draftProtocol->id,
            'catalog_item_id' => $this->roomType->id,
        ]);
        $item = ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $this->itemTemplate->id,
        ]);

        $response = $this->actingAs($this->user)->put(
            route('protocols.rooms.items.update', [$this->draftProtocol, $room, $item]),
            ['quantity' => 3, 'condition_catalog_item_id' => $this->conditionState->id]
        );

        $response->assertRedirect(route('protocols.show', $this->draftProtocol));
        $this->assertDatabaseHas('protocol_items', [
            'id' => $item->id,
            'quantity' => 3,
            'condition_catalog_item_id' => $this->conditionState->id,
        ]);
    }

    public function test_initiator_can_delete_item(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->draftProtocol->id,
            'catalog_item_id' => $this->roomType->id,
        ]);
        $item = ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $this->itemTemplate->id,
        ]);

        $response = $this->actingAs($this->user)->delete(
            route('protocols.rooms.items.destroy', [$this->draftProtocol, $room, $item])
        );

        $response->assertRedirect(route('protocols.show', $this->draftProtocol));
        $this->assertSoftDeleted('protocol_items', ['id' => $item->id]);
    }

    /* --- KRYTYCZNY GUARD: draft-only --- */

    public function test_adding_room_is_rejected_on_non_draft_protocol(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Protokół w toku',
            'status' => ProtocolStatus::PENDING_COUNTERPARTY,
        ]);

        $response = $this->actingAs($this->user)->post(
            route('protocols.rooms.store', $protocol),
            ['catalog_item_id' => $this->roomType->id]
        );

        $response->assertStatus(422);
        $this->assertDatabaseCount('protocol_rooms', 0);
    }

    public function test_deleting_room_is_rejected_on_non_draft_protocol(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Protokół podpisany',
            'status' => ProtocolStatus::SIGNED,
        ]);
        $room = ProtocolRoom::create([
            'protocol_id' => $protocol->id,
            'catalog_item_id' => $this->roomType->id,
        ]);

        $response = $this->actingAs($this->user)->delete(
            route('protocols.rooms.destroy', [$protocol, $room])
        );

        $response->assertStatus(422);
        $this->assertDatabaseHas('protocol_rooms', ['id' => $room->id, 'deleted_at' => null]);
    }

    public function test_adding_item_is_rejected_on_non_draft_protocol(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Protokół podpisany',
            'status' => ProtocolStatus::SIGNED,
        ]);
        $room = ProtocolRoom::create([
            'protocol_id' => $protocol->id,
            'catalog_item_id' => $this->roomType->id,
        ]);

        $response = $this->actingAs($this->user)->post(
            route('protocols.rooms.items.store', [$protocol, $room]),
            ['catalog_item_id' => $this->itemTemplate->id]
        );

        $response->assertStatus(422);
        $this->assertDatabaseCount('protocol_items', 0);
    }

    /* --- guard: not initiator --- */

    public function test_non_initiator_cannot_add_room(): void
    {
        $response = $this->actingAs($this->otherUser())->post(
            route('protocols.rooms.store', $this->draftProtocol),
            ['catalog_item_id' => $this->roomType->id]
        );

        $response->assertForbidden();
        $this->assertDatabaseCount('protocol_rooms', 0);
    }

    public function test_non_initiator_cannot_add_item(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->draftProtocol->id,
            'catalog_item_id' => $this->roomType->id,
        ]);

        $response = $this->actingAs($this->otherUser())->post(
            route('protocols.rooms.items.store', [$this->draftProtocol, $room]),
            ['catalog_item_id' => $this->itemTemplate->id]
        );

        $response->assertForbidden();
        $this->assertDatabaseCount('protocol_items', 0);
    }

    public function test_non_initiator_cannot_delete_room(): void
    {
        $room = ProtocolRoom::create([
            'protocol_id' => $this->draftProtocol->id,
            'catalog_item_id' => $this->roomType->id,
        ]);

        $response = $this->actingAs($this->otherUser())->delete(
            route('protocols.rooms.destroy', [$this->draftProtocol, $room])
        );

        $response->assertForbidden();
        $this->assertDatabaseHas('protocol_rooms', ['id' => $room->id, 'deleted_at' => null]);
    }

    /* --- validation --- */

    public function test_adding_room_requires_valid_room_type_catalog_item(): void
    {
        $itemTypeCatalogId = $this->itemTemplate->id; // wrong type: ITEM, not ROOM

        $response = $this->actingAs($this->user)->post(
            route('protocols.rooms.store', $this->draftProtocol),
            ['catalog_item_id' => $itemTypeCatalogId]
        );

        $response->assertSessionHasErrors(['catalog_item_id']);
        $this->assertDatabaseCount('protocol_rooms', 0);
    }

    /* --- guest --- */

    public function test_guest_cannot_add_room(): void
    {
        $response = $this->post(
            route('protocols.rooms.store', $this->draftProtocol),
            ['catalog_item_id' => $this->roomType->id]
        );

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('protocol_rooms', 0);
    }
}
