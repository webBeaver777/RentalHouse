<?php

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Domain\Enums\ReferenceMode;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M12, Scenario C slice 1: creating a check-out linked to a completed
 * check-in as baseline, and the "było/stało" comparison it renders.
 * Wires BaselineService::linkToBaseline() / ::createItemsFromBaseline()
 * (see ProtocolCheckOutController) — no new linking/copy logic.
 */
class CheckOutCreationHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Property $property;

    private Protocol $completedCheckIn;

    private ProtocolRoom $baselineRoom;

    private ProtocolItem $baselineItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->seed(CatalogSeeder::class);

        $this->landlord = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
        ]);

        $this->property = Property::create([
            'user_id' => $this->landlord->id,
            'name' => 'Mieszkanie Testowe',
            'street' => 'ul. Testowa',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->completedCheckIn = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::COMPLETED,
            'title' => 'Protokół wjazdu — baseline',
            'completed_at' => now()->subMonth(),
        ]);

        Participant::create([
            'protocol_id' => $this->completedCheckIn->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
            'invited_at' => now()->subMonth(),
            'accepted_at' => now()->subMonth(),
            'signed_at' => now()->subMonth(),
        ]);

        $roomType = CatalogItem::ofType(CatalogItemType::ROOM)->first();
        $itemTemplate = CatalogItem::ofType(CatalogItemType::ITEM)->first();
        $conditionState = CatalogItem::ofType(CatalogItemType::CONDITION)->first();

        $this->baselineRoom = ProtocolRoom::create([
            'protocol_id' => $this->completedCheckIn->id,
            'catalog_item_id' => $roomType->id,
            'custom_name' => 'Salon',
        ]);

        $this->baselineItem = ProtocolItem::create([
            'protocol_room_id' => $this->baselineRoom->id,
            'catalog_item_id' => $itemTemplate->id,
            'condition_catalog_item_id' => $conditionState->id,
            'quantity' => 1,
        ]);

        Evidence::create([
            'protocol_id' => $this->completedCheckIn->id,
            'evidenceable_type' => ProtocolItem::class,
            'evidenceable_id' => $this->baselineItem->id,
            'uploaded_by_user_id' => $this->landlord->id,
            'type' => 'photo',
            'filename' => 'baseline.jpg',
            'original_filename' => 'baseline.jpg',
            'path' => 'evidence/baseline.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'hash' => hash('sha256', 'baseline-photo'),
        ]);
    }

    private function otherUser(): User
    {
        return User::create([
            'name' => 'Anna Nowak',
            'email' => 'anna@example.com',
            'password' => 'password123',
        ]);
    }

    /* --- creation: only from a completed check-in --- */

    public function test_checkout_created_from_completed_checkin_copies_baseline(): void
    {
        $response = $this->actingAs($this->landlord)->post(route('protocols.checkout.store', $this->completedCheckIn));

        $checkOut = Protocol::where('type', ProtocolType::CHECK_OUT)->firstOrFail();
        $response->assertRedirect(route('protocols.show', $checkOut));

        $this->assertEquals($this->completedCheckIn->id, $checkOut->linked_checkin_id);
        $this->assertEquals(ReferenceMode::SYSTEM_BASELINE, $checkOut->reference_mode);
        $this->assertEquals(ParticipantRole::LANDLORD, $checkOut->initiator_role);
        $this->assertEquals(ParticipantRole::TENANT, $checkOut->counterparty_role);
        $this->assertEquals(ProtocolStatus::DRAFT, $checkOut->status);

        $checkOutRoom = ProtocolRoom::where('protocol_id', $checkOut->id)->firstOrFail();
        $this->assertEquals('Salon', $checkOutRoom->custom_name);

        $checkOutItem = ProtocolItem::where('protocol_room_id', $checkOutRoom->id)->firstOrFail();
        $this->assertEquals($this->baselineItem->id, $checkOutItem->baseline_item_id);
        $this->assertNull($checkOutItem->condition_catalog_item_id, 'current condition starts unset — "to be filled during check-out"');
    }

    public function test_checkout_creation_rejected_for_non_completed_checkin(): void
    {
        $draftCheckIn = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Draft',
        ]);

        $response = $this->actingAs($this->landlord)->post(route('protocols.checkout.store', $draftCheckIn));

        $response->assertStatus(422);
        $this->assertDatabaseCount('protocols', 2);
    }

    public function test_checkout_creation_rejected_for_non_owner(): void
    {
        $response = $this->actingAs($this->otherUser())->post(route('protocols.checkout.store', $this->completedCheckIn));

        $response->assertForbidden();
        $this->assertDatabaseCount('protocols', 1);
    }

    public function test_guest_cannot_create_checkout(): void
    {
        $response = $this->post(route('protocols.checkout.store', $this->completedCheckIn));

        $response->assertRedirect(route('login'));
    }

    /* --- baseline comparison rendered on the check-out's own show() --- */

    public function test_show_exposes_baseline_and_comparison_status(): void
    {
        $this->actingAs($this->landlord)->post(route('protocols.checkout.store', $this->completedCheckIn));
        $checkOut = Protocol::where('type', ProtocolType::CHECK_OUT)->firstOrFail();

        $response = $this->actingAs($this->landlord)->get(route('protocols.show', $checkOut));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Protocol/Show')
            ->where('protocol.is_check_out', true)
            ->where('protocol.linked_checkin.id', $this->completedCheckIn->id)
            ->where('protocol.rooms.0.items.0.comparison_status', 'unassessed')
            ->where('protocol.rooms.0.items.0.baseline.condition_name', $this->baselineItem->condition_name)
            ->where('protocol.rooms.0.items.0.baseline.photos.0.original_filename', 'baseline.jpg')
        );
    }

    /* --- draft-only + initiator guard on the reused item-update endpoint --- */

    public function test_initiator_can_set_current_condition_on_checkout_item_in_draft(): void
    {
        $this->actingAs($this->landlord)->post(route('protocols.checkout.store', $this->completedCheckIn));
        $checkOut = Protocol::where('type', ProtocolType::CHECK_OUT)->firstOrFail();
        $checkOutRoom = ProtocolRoom::where('protocol_id', $checkOut->id)->firstOrFail();
        $checkOutItem = ProtocolItem::where('protocol_room_id', $checkOutRoom->id)->firstOrFail();

        $differentCondition = CatalogItem::ofType(CatalogItemType::CONDITION)
            ->where('id', '!=', $this->baselineItem->condition_catalog_item_id)
            ->first();

        $response = $this->actingAs($this->landlord)->put(
            route('protocols.rooms.items.update', [$checkOut, $checkOutRoom, $checkOutItem]),
            ['condition_catalog_item_id' => $differentCondition->id]
        );

        $response->assertRedirect(route('protocols.show', $checkOut));
        $checkOutItem->refresh();
        $this->assertEquals($differentCondition->id, $checkOutItem->condition_catalog_item_id);

        // Reflected as "changed" via the show() comparison.
        $show = $this->actingAs($this->landlord)->get(route('protocols.show', $checkOut));
        $show->assertInertia(fn ($page) => $page
            ->where('protocol.rooms.0.items.0.comparison_status', 'changed')
        );
    }

    public function test_non_initiator_cannot_update_checkout_item(): void
    {
        $this->actingAs($this->landlord)->post(route('protocols.checkout.store', $this->completedCheckIn));
        $checkOut = Protocol::where('type', ProtocolType::CHECK_OUT)->firstOrFail();
        $checkOutRoom = ProtocolRoom::where('protocol_id', $checkOut->id)->firstOrFail();
        $checkOutItem = ProtocolItem::where('protocol_room_id', $checkOutRoom->id)->firstOrFail();

        $response = $this->actingAs($this->otherUser())->put(
            route('protocols.rooms.items.update', [$checkOut, $checkOutRoom, $checkOutItem]),
            ['condition_catalog_item_id' => $this->baselineItem->condition_catalog_item_id]
        );

        $response->assertForbidden();
    }
}
