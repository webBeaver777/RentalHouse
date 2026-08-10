<?php

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Modules\Acceptance\Infrastructure\Models\Acceptance;
use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Document\Domain\Enums\PdfTemplateType;
use App\Modules\Document\Infrastructure\Models\GeneratedDocument;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\LegalMode;
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
 * M13 step 3/6, Scenario C slice 2: "Wydaj akt" exercised through the real
 * HTTP controller (ProtocolIssueCheckOutController), unlike the
 * domain-level ScenarioCTest/LegalModeDomainActionsTest which call
 * IssueCheckOutAction directly — this proves the hard-gate, one-acceptance
 * issuance (Guard 1), legal_mode, check-out PDF template, and post-issuance
 * immutability all actually reach the HTTP layer.
 */
class CheckOutIssueHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Property $property;

    private Protocol $checkOut;

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

        $this->tenant = User::create([
            'name' => 'Anna Nowak',
            'email' => 'anna@example.com',
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

        $completedCheckIn = Protocol::create([
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
            'protocol_id' => $completedCheckIn->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
            'invited_at' => now()->subMonth(),
            'accepted_at' => now()->subMonth(),
            'signed_at' => now()->subMonth(),
        ]);

        Participant::create([
            'protocol_id' => $completedCheckIn->id,
            'user_id' => $this->tenant->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
            'invited_at' => now()->subMonth(),
            'accepted_at' => now()->subMonth(),
            'signed_at' => now()->subMonth(),
        ]);

        $roomType = CatalogItem::ofType(CatalogItemType::ROOM)->first();
        $itemTemplate = CatalogItem::ofType(CatalogItemType::ITEM)->first();

        $baselineRoom = ProtocolRoom::create([
            'protocol_id' => $completedCheckIn->id,
            'catalog_item_id' => $roomType->id,
            'custom_name' => 'Salon',
        ]);

        ProtocolItem::create([
            'protocol_room_id' => $baselineRoom->id,
            'catalog_item_id' => $itemTemplate->id,
            'quantity' => 1,
        ]);

        // C1: create the check-out draft through the real HTTP endpoint.
        $this->actingAs($this->landlord)->post(route('protocols.checkout.store', $completedCheckIn));
        $this->checkOut = Protocol::where('type', ProtocolType::CHECK_OUT)->firstOrFail();
    }

    private function grantCheckOutEntitlement(): void
    {
        Entitlement::create([
            'user_id' => $this->landlord->id,
            'product_code' => ProductCode::WYJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_OUT,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);
    }

    /* --- hard-gate: no entitlement -> rejected, still draft --- */

    public function test_issue_without_entitlement_is_rejected(): void
    {
        $response = $this->actingAs($this->landlord)->post(route('protocols.checkout.issue', $this->checkOut));

        $response->assertSessionHasErrors(['issue']);
        $this->checkOut->refresh();
        $this->assertEquals(ProtocolStatus::DRAFT, $this->checkOut->status);
        $this->assertNull($this->checkOut->act_issued_at);
    }

    /* --- Guard 1: one acceptance (the initiator's) is enough to issue --- */

    public function test_issue_succeeds_with_only_initiator_acceptance(): void
    {
        $this->grantCheckOutEntitlement();

        $response = $this->actingAs($this->landlord)->post(route('protocols.checkout.issue', $this->checkOut));

        $response->assertRedirect(route('protocols.show', $this->checkOut));

        $this->checkOut->refresh();
        $this->assertEquals(ProtocolStatus::COMPLETED, $this->checkOut->status);
        $this->assertNotNull($this->checkOut->act_issued_at);
        $this->assertNotNull($this->checkOut->objection_window_ends_at);
        $this->assertTrue($this->checkOut->isObjectionWindowOpen());

        // M13 step 0: legal_mode set by the domain action itself, unilateral
        // (landlord initiator here).
        $this->assertEquals(LegalMode::UNILATERAL_LANDLORD, $this->checkOut->legal_mode);
        $this->assertFalse($this->checkOut->legal_mode->requiresBothSignatures());

        // Guard 1: exactly ONE acceptance — the counterparty never signs.
        $this->assertEquals(1, Acceptance::where('protocol_id', $this->checkOut->id)->count());

        // Informational magic-link created for the counterparty (dev).
        $this->checkOut->refresh();
        $this->assertNotNull($this->checkOut->metadata['dev_magic_link'] ?? null);
        $tenantParticipant = Participant::where('protocol_id', $this->checkOut->id)
            ->where('role', ParticipantRole::TENANT)
            ->first();
        $this->assertNotNull($tenantParticipant);
        $this->assertNull($tenantParticipant->signed_at, 'counterparty is notified, never required to sign');
    }

    /* --- PDF: check-out template, not check-in; document_hash frozen --- */

    public function test_issue_generates_checkout_pdf_and_freezes_hash(): void
    {
        $this->grantCheckOutEntitlement();

        $this->actingAs($this->landlord)->post(route('protocols.checkout.issue', $this->checkOut));

        $this->checkOut->refresh();
        $this->assertNotNull($this->checkOut->document_hash);

        $document = GeneratedDocument::where('protocol_id', $this->checkOut->id)->firstOrFail();
        $this->assertEquals(PdfTemplateType::CHECKOUT_LANDLORD->value, $document->template_type);
        $this->assertEquals($this->checkOut->document_hash, $document->hash);
    }

    /**
     * Regression: PdfGenerationService eager-loads 'defects.evidence' — a real
     * withholding present on the protocol must not break PDF generation
     * (caught in browser proof: the eager load previously referenced a
     * non-existent 'defects.photos' relation on ProtocolDefect, which only
     * throws once a defect row actually exists to eager-load into).
     */
    public function test_issue_generates_pdf_with_a_withholding_present(): void
    {
        $room = ProtocolRoom::where('protocol_id', $this->checkOut->id)->firstOrFail();
        $item = ProtocolItem::where('protocol_room_id', $room->id)->firstOrFail();

        $this->actingAs($this->landlord)->post(
            route('protocols.rooms.items.defects.store', [$this->checkOut, $room, $item]),
            ['title' => 'Zarysowana ściana', 'estimated_cost' => 450]
        );

        $this->grantCheckOutEntitlement();

        $response = $this->actingAs($this->landlord)->post(route('protocols.checkout.issue', $this->checkOut));

        $response->assertRedirect(route('protocols.show', $this->checkOut));
        $this->checkOut->refresh();
        $this->assertNotNull($this->checkOut->document_hash);
        $this->assertEquals(450.00, $this->checkOut->total_damage_cost);
    }

    /* --- immutability: sealed after issuance --- */

    public function test_content_is_immutable_after_act_is_issued(): void
    {
        $this->grantCheckOutEntitlement();
        $this->actingAs($this->landlord)->post(route('protocols.checkout.issue', $this->checkOut));

        $room = ProtocolRoom::where('protocol_id', $this->checkOut->id)->firstOrFail();
        $item = ProtocolItem::where('protocol_room_id', $room->id)->firstOrFail();
        $conditionState = CatalogItem::ofType(CatalogItemType::CONDITION)->first();

        // Structure/condition mutation rejected.
        $itemUpdate = $this->actingAs($this->landlord)->put(
            route('protocols.rooms.items.update', [$this->checkOut, $room, $item]),
            ['condition_catalog_item_id' => $conditionState->id]
        );
        $itemUpdate->assertStatus(422);

        // Deposit amount mutation rejected.
        $depositUpdate = $this->actingAs($this->landlord)->put(
            route('protocols.deposit.update', $this->checkOut),
            ['deposit_amount' => 1234.56]
        );
        $depositUpdate->assertStatus(422);

        // New withholding rejected.
        $defectStore = $this->actingAs($this->landlord)->post(
            route('protocols.rooms.items.defects.store', [$this->checkOut, $room, $item]),
            ['title' => 'Uszkodzenie', 'estimated_cost' => 100]
        );
        $defectStore->assertStatus(422);

        // Re-issuing is rejected outright.
        $reissue = $this->actingAs($this->landlord)->post(route('protocols.checkout.issue', $this->checkOut));
        $reissue->assertStatus(422);
    }

    /* --- deposit + withholdings tracked before issuance, reflected after --- */

    public function test_deposit_and_withholding_are_recorded_before_issuance(): void
    {
        $room = ProtocolRoom::where('protocol_id', $this->checkOut->id)->firstOrFail();
        $item = ProtocolItem::where('protocol_room_id', $room->id)->firstOrFail();

        $this->actingAs($this->landlord)->put(
            route('protocols.deposit.update', $this->checkOut),
            ['deposit_amount' => 3000]
        );

        $this->actingAs($this->landlord)->post(
            route('protocols.rooms.items.defects.store', [$this->checkOut, $room, $item]),
            ['title' => 'Zarysowana podłoga', 'estimated_cost' => 450.50]
        );

        $this->checkOut->refresh();
        $this->assertEquals(3000.00, $this->checkOut->deposit_amount);
        $this->assertEquals(450.50, $this->checkOut->total_damage_cost);
        $this->assertEquals(2549.50, $this->checkOut->amount_to_return);
    }
}
