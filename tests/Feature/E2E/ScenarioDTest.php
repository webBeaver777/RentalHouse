<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Modules\Acceptance\Application\Actions\SignProtocolAction;
use App\Modules\Acceptance\Infrastructure\Models\Acceptance;
use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Application\Services\InvitationService;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Application\Actions\IssueCheckOutAction;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Domain\Enums\ReferenceDocumentType;
use App\Modules\Protocol\Domain\Enums\ReferenceMode;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ReferenceDocument;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2E Scenario D: Check-out without Baseline (No Reference / Uploaded Reference)
 *
 * §25 G7 / §26.7: Check-out with uploaded_reference or no_reference mode
 * 1. Landlord creates check-out with no linked check-in
 * 2. Either uploads reference documents OR proceeds without baseline
 * 3. Landlord signs (unilateral)
 * 4. Act is issued with objection window
 *
 * Tests:
 * - UPLOADED_REFERENCE mode with reference documents
 * - NO_REFERENCE mode standalone
 */
class ScenarioDTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Property $property;

    private InvitationService $invitationService;

    private SignProtocolAction $signAction;

    private IssueCheckOutAction $issueAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([LocaleSeeder::class, CatalogSeeder::class]);

        $this->landlord = User::create([
            'name' => 'Jan Wynajmujący',
            'email' => 'landlord@example.com',
            'password' => 'password123',
        ]);

        $this->tenant = User::create([
            'name' => 'Anna Najemca',
            'email' => 'tenant@example.com',
            'password' => 'password123',
        ]);

        $this->property = Property::create([
            'user_id' => $this->landlord->id,
            'name' => 'Mieszkanie przy ul. Testowej',
            'street' => 'ul. Testowa',
            'building_number' => '15',
            'apartment_number' => '3A',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'country' => 'PL',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        // Create entitlement for check-out
        Entitlement::create([
            'user_id' => $this->landlord->id,
            'product_code' => ProductCode::WYJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_OUT,
            'quantity_total' => 2, // Two check-outs
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        $this->invitationService = app(InvitationService::class);
        $this->signAction = app(SignProtocolAction::class);
        $this->issueAction = app(IssueCheckOutAction::class);
    }

    /**
     * Full E2E Scenario D-1: Check-out with NO_REFERENCE mode.
     */
    public function test_scenario_d_checkout_no_reference_full_flow(): void
    {
        // Step 1: Create check-out with NO_REFERENCE mode
        $checkOut = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Check-out bez referencji - ul. Testowa 15/3A',
            'reference_mode' => ReferenceMode::NO_REFERENCE,
            'linked_checkin_id' => null, // No baseline
            'deposit_amount' => 2500.00,
            'locale' => 'pl',
        ]);

        $this->assertDatabaseHas('protocols', [
            'id' => $checkOut->id,
            'type' => 'check_out',
            'reference_mode' => 'no_reference',
            'linked_checkin_id' => null,
        ]);

        // Verify reference mode
        $this->assertEquals(ReferenceMode::NO_REFERENCE, $checkOut->reference_mode);
        $this->assertFalse($checkOut->reference_mode->requiresLinkedCheckin());
        $this->assertNull($checkOut->linkedCheckin);

        // Step 2: Add landlord as initiator
        $landlordParticipant = $this->invitationService->addInitiator(
            $checkOut,
            $this->landlord,
            ParticipantRole::LANDLORD
        );

        // Step 3: Notify tenant (optional for check-out)
        $invitationToken = $this->invitationService->inviteByEmail(
            $checkOut,
            $this->tenant->email,
            ParticipantRole::TENANT
        );

        // Step 4: Transition to pending_signatures (via pending_counterparty)
        $checkOut->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
        $checkOut->transitionTo(ProtocolStatus::PENDING_SIGNATURES);

        // Step 5: Landlord signs
        $checkOut = $this->signAction->execute(
            $checkOut,
            $landlordParticipant,
            'landlord-signature-no-ref',
            '192.168.1.100',
            'Mozilla/5.0',
            'landlord-fp'
        );

        $checkOut->refresh();
        $this->assertEquals(ProtocolStatus::SIGNED, $checkOut->status);

        // Step 6: Issue the check-out act
        $checkOut = $this->issueAction->execute($checkOut, 72);

        // Step 7: Verify final state
        $checkOut->refresh();
        $this->assertEquals(ProtocolStatus::COMPLETED, $checkOut->status);
        $this->assertNotNull($checkOut->act_issued_at);
        $this->assertNotNull($checkOut->objection_window_ends_at);

        // Verify timeline
        $events = InspectionEvent::where('protocol_id', $checkOut->id)
            ->pluck('event_type')
            ->filter()
            ->map(fn ($t) => $t->value)
            ->toArray();

        $this->assertContains('act_issued', $events);
        $this->assertContains('objection_window_opened', $events);

        // Verify only landlord acceptance (unilateral)
        $acceptances = Acceptance::where('protocol_id', $checkOut->id)->get();
        $this->assertEquals(1, $acceptances->count());
        $this->assertEquals(ParticipantRole::LANDLORD, $acceptances->first()->accepted_by_role);
    }

    /**
     * Full E2E Scenario D-2: Check-out with UPLOADED_REFERENCE mode.
     */
    public function test_scenario_d_checkout_uploaded_reference_full_flow(): void
    {
        // Step 1: Create check-out with UPLOADED_REFERENCE mode
        $checkOut = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Check-out z dokumentem - ul. Testowa 15/3A',
            'reference_mode' => ReferenceMode::UPLOADED_REFERENCE,
            'linked_checkin_id' => null,
            'deposit_amount' => 3500.00,
            'locale' => 'pl',
        ]);

        $this->assertEquals(ReferenceMode::UPLOADED_REFERENCE, $checkOut->reference_mode);
        $this->assertTrue($checkOut->reference_mode->supportsUploadedReference());

        // Step 2: Upload reference documents
        $paperProtocol = ReferenceDocument::create([
            'protocol_id' => $checkOut->id,
            'type' => ReferenceDocumentType::PAPER_PROTOCOL,
            'title' => 'Protokół papierowy 2022',
            'filename' => 'paper_protocol_001.pdf',
            'original_filename' => 'protokol_papierowy.pdf',
            'path' => 'references/paper_protocol_001.pdf',
            'mime_type' => 'application/pdf',
            'size' => 102400,
            'hash' => hash('sha256', 'paper_protocol_content'),
            'uploaded_at' => now(),
            'note' => 'Oryginalny protokół zdawczo-odbiorczy z 2022 roku',
            'sort_order' => 1,
        ]);

        $leaseContract = ReferenceDocument::create([
            'protocol_id' => $checkOut->id,
            'type' => ReferenceDocumentType::LEASE_CONTRACT,
            'title' => 'Umowa najmu',
            'filename' => 'lease_contract_001.pdf',
            'original_filename' => 'umowa_najmu.pdf',
            'path' => 'references/lease_contract_001.pdf',
            'mime_type' => 'application/pdf',
            'size' => 204800,
            'hash' => hash('sha256', 'lease_contract_content'),
            'uploaded_at' => now(),
            'note' => 'Umowa najmu z dnia 01.01.2022',
            'sort_order' => 2,
        ]);

        $photos = ReferenceDocument::create([
            'protocol_id' => $checkOut->id,
            'type' => ReferenceDocumentType::PHOTO,
            'title' => 'Zdjęcia z wprowadzenia',
            'filename' => 'photos_checkin.zip',
            'original_filename' => 'zdjecia_wprowadzenie.zip',
            'path' => 'references/photos_checkin.zip',
            'mime_type' => 'application/zip',
            'size' => 5242880,
            'hash' => hash('sha256', 'photos_archive_content'),
            'uploaded_at' => now(),
            'note' => 'Archiwum zdjęć z wprowadzenia',
            'sort_order' => 3,
        ]);

        // Verify reference documents are linked
        $this->assertEquals(3, $checkOut->referenceDocuments()->count());

        // Step 3: Add landlord as initiator
        $landlordParticipant = $this->invitationService->addInitiator(
            $checkOut,
            $this->landlord,
            ParticipantRole::LANDLORD
        );

        // Step 4: Transition and sign (via pending_counterparty)
        $checkOut->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
        $checkOut->transitionTo(ProtocolStatus::PENDING_SIGNATURES);

        $checkOut = $this->signAction->execute(
            $checkOut,
            $landlordParticipant,
            'landlord-signature-uploaded-ref',
            '192.168.1.100',
            'Mozilla/5.0',
            'landlord-fp'
        );

        // Step 5: Issue the act
        $checkOut = $this->issueAction->execute($checkOut, 48); // 48h window

        // Step 6: Verify final state
        $checkOut->refresh();
        $this->assertEquals(ProtocolStatus::COMPLETED, $checkOut->status);
        $this->assertNotNull($checkOut->act_issued_at);

        // Verify reference documents are still accessible
        $this->assertEquals(3, $checkOut->referenceDocuments()->count());

        // Verify document types
        $docTypes = $checkOut->referenceDocuments()->pluck('type')->map(fn ($t) => $t->value)->toArray();
        $this->assertContains('paper_protocol', $docTypes);
        $this->assertContains('lease_contract', $docTypes);
        $this->assertContains('photo', $docTypes);
    }

    /**
     * §26.7: Test all three reference modes are supported.
     */
    public function test_scenario_d_guard_7_all_reference_modes_work(): void
    {
        // Mode 1: NO_REFERENCE
        $noRef = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'No reference',
            'reference_mode' => ReferenceMode::NO_REFERENCE,
        ]);

        $this->assertEquals(ReferenceMode::NO_REFERENCE, $noRef->reference_mode);
        $this->assertFalse($noRef->reference_mode->requiresLinkedCheckin());
        $this->assertFalse($noRef->reference_mode->supportsUploadedReference());

        // Mode 2: UPLOADED_REFERENCE
        $uploadedRef = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Uploaded reference',
            'reference_mode' => ReferenceMode::UPLOADED_REFERENCE,
        ]);

        $this->assertEquals(ReferenceMode::UPLOADED_REFERENCE, $uploadedRef->reference_mode);
        $this->assertFalse($uploadedRef->reference_mode->requiresLinkedCheckin());
        $this->assertTrue($uploadedRef->reference_mode->supportsUploadedReference());

        // Mode 3: SYSTEM_BASELINE (requires linked check-in)
        $systemBaseline = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'System baseline',
            'reference_mode' => ReferenceMode::SYSTEM_BASELINE,
        ]);

        $this->assertEquals(ReferenceMode::SYSTEM_BASELINE, $systemBaseline->reference_mode);
        $this->assertTrue($systemBaseline->reference_mode->requiresLinkedCheckin());
        $this->assertFalse($systemBaseline->reference_mode->supportsUploadedReference());
    }

    /**
     * Test reference document types enum.
     */
    public function test_scenario_d_reference_document_types(): void
    {
        $checkOut = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Doc types test',
            'reference_mode' => ReferenceMode::UPLOADED_REFERENCE,
        ]);

        // Test all reference document types
        $types = [
            ReferenceDocumentType::PDF,
            ReferenceDocumentType::PHOTO,
            ReferenceDocumentType::PAPER_PROTOCOL,
            ReferenceDocumentType::LEASE_CONTRACT,
            ReferenceDocumentType::OTHER,
        ];

        foreach ($types as $index => $type) {
            ReferenceDocument::create([
                'protocol_id' => $checkOut->id,
                'type' => $type,
                'title' => "Test document {$type->value}",
                'filename' => "test_{$type->value}.pdf",
                'original_filename' => "test_{$type->value}.pdf",
                'path' => "references/test_{$type->value}.pdf",
                'mime_type' => 'application/pdf',
                'size' => 51200,
                'hash' => hash('sha256', "content_{$type->value}"),
                'uploaded_at' => now(),
                'sort_order' => $index,
            ]);
        }

        $this->assertEquals(5, $checkOut->referenceDocuments()->count());
    }

    /**
     * Test reference documents have required fields.
     */
    public function test_scenario_d_reference_documents_have_hash(): void
    {
        $checkOut = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Hash test',
            'reference_mode' => ReferenceMode::UPLOADED_REFERENCE,
        ]);

        $doc = ReferenceDocument::create([
            'protocol_id' => $checkOut->id,
            'type' => ReferenceDocumentType::PDF,
            'title' => 'Test PDF document',
            'filename' => 'test.pdf',
            'original_filename' => 'test.pdf',
            'path' => 'references/test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 25600,
            'hash' => hash('sha256', 'test_content_for_hash'),
            'uploaded_at' => now(),
            'note' => 'Test document',
            'sort_order' => 1,
        ]);

        $this->assertNotNull($doc->hash);
        $this->assertEquals(64, strlen($doc->hash)); // SHA-256 is 64 hex chars
        $this->assertNotNull($doc->path);
        $this->assertNotNull($doc->original_filename);
    }

    /**
     * Test tenant-initiated check-out also works.
     */
    public function test_scenario_d_tenant_initiated_checkout(): void
    {
        // Tenant-declared property
        $tenantProperty = Property::create([
            'user_id' => $this->tenant->id,
            'name' => 'Wynajmowane mieszkanie',
            'street' => 'ul. Najemcy',
            'building_number' => '10',
            'city' => 'Poznań',
            'postal_code' => '60-001',
            'declaration_type' => DeclarationType::TENANT_DECLARED,
        ]);

        // Give tenant entitlement
        Entitlement::create([
            'user_id' => $this->tenant->id,
            'product_code' => ProductCode::WYJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_OUT,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        $checkOut = Protocol::create([
            'property_id' => $tenantProperty->id,
            'created_by_user_id' => $this->tenant->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::TENANT,
            'counterparty_role' => ParticipantRole::LANDLORD,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Tenant-initiated checkout',
            'reference_mode' => ReferenceMode::NO_REFERENCE,
        ]);

        $this->assertEquals(ParticipantRole::TENANT, $checkOut->initiator_role);
        $this->assertEquals(ParticipantRole::LANDLORD, $checkOut->counterparty_role);

        // Add tenant as initiator
        $tenantParticipant = $this->invitationService->addInitiator(
            $checkOut,
            $this->tenant,
            ParticipantRole::TENANT
        );

        $checkOut->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
        $checkOut->transitionTo(ProtocolStatus::PENDING_SIGNATURES);

        // Tenant signs
        $checkOut = $this->signAction->execute(
            $checkOut,
            $tenantParticipant,
            'tenant-signature',
            '192.168.1.200',
            'Tenant Browser',
            'tenant-fp'
        );

        // Issue act
        $checkOut = $this->issueAction->execute($checkOut);

        $checkOut->refresh();
        $this->assertEquals(ProtocolStatus::COMPLETED, $checkOut->status);

        // Verify tenant acceptance
        $acceptance = Acceptance::where('protocol_id', $checkOut->id)->first();
        $this->assertEquals(ParticipantRole::TENANT, $acceptance->accepted_by_role);
    }

    /**
     * Test objection window without baseline still works.
     */
    public function test_scenario_d_objection_window_without_baseline(): void
    {
        $checkOut = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::SIGNED,
            'title' => 'Objection window test',
            'reference_mode' => ReferenceMode::NO_REFERENCE,
        ]);

        Participant::create([
            'protocol_id' => $checkOut->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
            'signed_at' => now(),
        ]);

        $checkOut = $this->issueAction->execute($checkOut, 24); // 24h window

        $checkOut->refresh();
        $this->assertTrue($checkOut->isObjectionWindowOpen());
        $this->assertGreaterThanOrEqual(23, $checkOut->remainingObjectionHours());
        $this->assertLessThanOrEqual(24, $checkOut->remainingObjectionHours());

        // Verify objection window event
        $windowEvent = InspectionEvent::where('protocol_id', $checkOut->id)
            ->where('event_type', InspectionEventType::OBJECTION_WINDOW_OPENED)
            ->first();

        $this->assertNotNull($windowEvent);
        $this->assertArrayHasKey('ends_at', $windowEvent->payload);
    }
}
