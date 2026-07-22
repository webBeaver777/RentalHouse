<?php

namespace Tests\Feature\Acceptance;

use App\Modules\Acceptance\Application\Services\AcceptanceService;
use App\Modules\Acceptance\Domain\Enums\AcceptanceStatus;
use App\Modules\Acceptance\Infrastructure\Models\ItemAcceptance;
use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;
    private User $tenant;
    private Protocol $protocol;
    private Participant $landlordParticipant;
    private Participant $tenantParticipant;
    private ProtocolItem $item;
    private AcceptanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);

        $this->landlord = User::create([
            'name' => 'Landlord',
            'email' => 'landlord@example.com',
            'password' => 'password123',
        ]);

        $this->tenant = User::create([
            'name' => 'Tenant',
            'email' => 'tenant@example.com',
            'password' => 'password123',
        ]);

        $property = Property::create([
            'user_id' => $this->landlord->id,
            'name' => 'Test Property',
            'street' => 'Test Street',
            'building_number' => '1',
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->protocol = Protocol::create([
            'property_id' => $property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Test Protocol',
        ]);

        $this->landlordParticipant = Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        $this->tenantParticipant = Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->tenant->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
        ]);

        $roomCatalog = CatalogItem::ofType(CatalogItemType::ROOM)->first();
        $itemCatalog = CatalogItem::ofType(CatalogItemType::ITEM)->first();

        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $roomCatalog->id,
        ]);

        $this->item = ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $itemCatalog->id,
        ]);

        $this->service = new AcceptanceService();
    }

    /**
     * Item acceptance can be created.
     */
    public function test_item_acceptance_can_be_created(): void
    {
        $acceptance = ItemAcceptance::create([
            'protocol_id' => $this->protocol->id,
            'protocol_item_id' => $this->item->id,
            'participant_id' => $this->tenantParticipant->id,
            'status' => AcceptanceStatus::PENDING,
        ]);

        $this->assertDatabaseHas('item_acceptances', [
            'id' => $acceptance->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Acceptance defaults to pending.
     */
    public function test_acceptance_defaults_to_pending(): void
    {
        $acceptance = ItemAcceptance::create([
            'protocol_id' => $this->protocol->id,
            'protocol_item_id' => $this->item->id,
            'participant_id' => $this->tenantParticipant->id,
        ]);

        $this->assertEquals(AcceptanceStatus::PENDING, $acceptance->status);
        $this->assertTrue($acceptance->isPending());
    }

    /**
     * Item can be accepted.
     */
    public function test_item_can_be_accepted(): void
    {
        $acceptance = $this->service->acceptItem($this->item, $this->tenantParticipant);

        $this->assertTrue($acceptance->isAccepted());
        $this->assertNotNull($acceptance->resolved_at);
    }

    /**
     * Item can be disputed.
     */
    public function test_item_can_be_disputed(): void
    {
        $acceptance = $this->service->disputeItem(
            $this->item,
            $this->tenantParticipant,
            'Ściany są porysowane'
        );

        $this->assertTrue($acceptance->isDisputed());
        $this->assertEquals('Ściany są porysowane', $acceptance->dispute_reason);
        $this->assertNull($acceptance->resolved_at);
    }

    /**
     * Dispute can be resolved.
     */
    public function test_dispute_can_be_resolved(): void
    {
        $acceptance = $this->service->disputeItem(
            $this->item,
            $this->tenantParticipant,
            'Problem'
        );

        $this->service->resolveDispute($acceptance, 'Naprawiono przed przekazaniem');

        $this->assertTrue($acceptance->isResolved());
        $this->assertEquals('Naprawiono przed przekazaniem', $acceptance->resolution_notes);
    }

    /**
     * Item has acceptance relationship.
     */
    public function test_item_has_acceptance_relationship(): void
    {
        $this->service->acceptItem($this->item, $this->tenantParticipant);
        $this->service->acceptItem($this->item, $this->landlordParticipant);

        $this->assertEquals(2, $this->item->acceptances()->count());
    }

    /**
     * Can check if item is accepted by participant.
     */
    public function test_can_check_item_accepted_by_participant(): void
    {
        $this->service->acceptItem($this->item, $this->tenantParticipant);

        $this->assertTrue($this->item->isAcceptedBy($this->tenantParticipant));
        $this->assertFalse($this->item->isAcceptedBy($this->landlordParticipant));
    }

    /**
     * Can check if item is fully accepted.
     */
    public function test_can_check_item_fully_accepted(): void
    {
        $this->assertFalse($this->item->isFullyAccepted());

        $this->service->acceptItem($this->item, $this->tenantParticipant);
        $this->assertFalse($this->item->fresh()->isFullyAccepted());

        $this->service->acceptItem($this->item, $this->landlordParticipant);
        $this->assertTrue($this->item->fresh()->isFullyAccepted());
    }

    /**
     * Can check if item has disputes.
     */
    public function test_can_check_item_has_disputes(): void
    {
        $this->assertFalse($this->item->hasDisputes());

        $this->service->disputeItem($this->item, $this->tenantParticipant, 'Problem');
        $this->assertTrue($this->item->fresh()->hasDisputes());
    }

    /**
     * Can accept all items.
     */
    public function test_can_accept_all_items(): void
    {
        // Create additional item
        $room = $this->item->room;
        $itemCatalog = CatalogItem::ofType(CatalogItemType::ITEM)->first();

        ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $itemCatalog->id,
        ]);

        $acceptances = $this->service->acceptAllItems($this->protocol, $this->tenantParticipant);

        $this->assertCount(2, $acceptances);
        $this->assertTrue($acceptances->every(fn($a) => $a->isAccepted()));
    }

    /**
     * Can get protocol summary.
     */
    public function test_can_get_protocol_summary(): void
    {
        $this->service->acceptItem($this->item, $this->landlordParticipant);
        $this->service->disputeItem($this->item, $this->tenantParticipant, 'Problem');

        $summary = $this->service->getProtocolSummary($this->protocol);

        $this->assertEquals(1, $summary['total_items']);
        $this->assertEquals(1, $summary['accepted']);
        $this->assertEquals(1, $summary['disputed']);
        $this->assertEquals(1, $summary['unresolved_disputes']);
    }

    /**
     * Can get participant summary.
     */
    public function test_can_get_participant_summary(): void
    {
        // Create second item
        $room = $this->item->room;
        $itemCatalog = CatalogItem::ofType(CatalogItemType::ITEM)->first();

        ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $itemCatalog->id,
        ]);

        $this->service->acceptItem($this->item, $this->tenantParticipant);

        $summary = $this->service->getParticipantSummary($this->protocol, $this->tenantParticipant);

        $this->assertEquals(2, $summary['total_items']);
        $this->assertEquals(1, $summary['reviewed']);
        $this->assertEquals(1, $summary['pending']);
        $this->assertEquals(1, $summary['accepted']);
        $this->assertEquals(50, $summary['progress_percent']);
    }

    /**
     * Can check if protocol is fully accepted.
     */
    public function test_can_check_protocol_fully_accepted(): void
    {
        $this->assertFalse($this->service->isProtocolFullyAccepted($this->protocol));

        $this->service->acceptItem($this->item, $this->landlordParticipant);
        $this->assertFalse($this->service->isProtocolFullyAccepted($this->protocol));

        $this->service->acceptItem($this->item, $this->tenantParticipant);
        $this->assertTrue($this->service->isProtocolFullyAccepted($this->protocol));
    }

    /**
     * Can check for unresolved disputes.
     */
    public function test_can_check_unresolved_disputes(): void
    {
        $this->assertFalse($this->service->hasUnresolvedDisputes($this->protocol));

        $acceptance = $this->service->disputeItem($this->item, $this->tenantParticipant, 'Problem');
        $this->assertTrue($this->service->hasUnresolvedDisputes($this->protocol));

        $this->service->resolveDispute($acceptance, 'Resolved');
        $this->assertFalse($this->service->hasUnresolvedDisputes($this->protocol));
    }

    /**
     * Can get disputed items.
     */
    public function test_can_get_disputed_items(): void
    {
        $this->service->disputeItem($this->item, $this->tenantParticipant, 'Problem');

        $disputed = $this->service->getDisputedItems($this->protocol);

        $this->assertCount(1, $disputed);
        $this->assertEquals($this->item->id, $disputed->first()->id);
    }

    /**
     * Scopes work correctly.
     */
    public function test_scopes_work(): void
    {
        $this->service->acceptItem($this->item, $this->landlordParticipant);
        $this->service->disputeItem($this->item, $this->tenantParticipant, 'Problem');

        $this->assertEquals(1, ItemAcceptance::accepted()->count());
        $this->assertEquals(1, ItemAcceptance::disputed()->count());
        $this->assertEquals(1, ItemAcceptance::unresolvedDisputes()->count());
    }
}
