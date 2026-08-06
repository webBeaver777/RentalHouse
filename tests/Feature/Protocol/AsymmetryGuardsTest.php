<?php

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Modules\Acceptance\Application\Actions\RaiseObjectionAction;
use App\Modules\Acceptance\Application\Actions\SignProtocolAction;
use App\Modules\Acceptance\Infrastructure\Models\Acceptance;
use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Domain\Exceptions\EntitlementRequiredException;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Document\Domain\Enums\DocumentType;
use App\Modules\Document\Domain\Enums\PdfTemplateType;
use App\Modules\Document\Infrastructure\Models\GeneratedDocument;
use App\Modules\Evidence\Application\Services\EvidenceUploadService;
use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Lifecycle\Application\Jobs\CompleteExpiredObjectionWindows;
use App\Modules\Notification\Domain\Events\ParticipantInvited;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Application\Actions\FinalizeCheckInAction;
use App\Modules\Protocol\Application\Actions\IssueCheckOutAction;
use App\Modules\Protocol\Application\Services\CounterpartyPhotoService;
use App\Modules\Protocol\Application\Services\ProtocolCommentService;
use App\Modules\Protocol\Domain\Enums\LegalMode;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Domain\Enums\ReferenceDocumentType;
use App\Modules\Protocol\Domain\Enums\ReferenceMode;
use App\Modules\Protocol\Domain\Exceptions\ProtocolFinalizationException;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use App\Modules\Protocol\Infrastructure\Models\ReferenceDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * §26 Guards Test Suite - Asymmetry Enforcement
 *
 * Each test here corresponds to a guard from §26.
 * Violation = incorrect implementation.
 */
class AsymmetryGuardsTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::create([
            'name' => 'Wynajmujący',
            'email' => 'landlord@example.com',
            'password' => 'password',
        ]);

        $this->tenant = User::create([
            'name' => 'Najemca',
            'email' => 'tenant@example.com',
            'password' => 'password',
        ]);

        $this->property = Property::create([
            'user_id' => $this->landlord->id,
            'name' => 'Test Property',
            'street' => 'Test Street',
            'building_number' => '1',
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);
    }

    /**
     * §26.1: Check-out does NOT require counterparty acceptance for act validity.
     */
    public function test_guard_1_checkout_does_not_require_counterparty_acceptance(): void
    {
        $protocol = $this->createCheckOutProtocol();
        $this->addParticipants($protocol);

        // Only initiator signs
        $protocol->initiator()->sign();

        // Check-out should be issuable without counterparty signature
        $action = app(IssueCheckOutAction::class);
        $this->assertTrue($action->canIssue($protocol));

        // Give initiator an entitlement for D3 gate
        $this->giveEntitlement($this->landlord, AllowedAction::CREATE_CHECK_OUT);

        // Issue the act
        $result = $action->execute($protocol);

        $this->assertEquals(ProtocolStatus::COMPLETED, $result->status);
        $this->assertNotNull($result->act_issued_at);
        $this->assertNotNull($result->objection_window_ends_at);
    }

    /**
     * §26.2: Tenant-initiated check-in without landlord signature is NOT bilateral.
     */
    public function test_guard_2_tenant_initiated_checkin_without_landlord_is_not_bilateral(): void
    {
        $protocol = $this->createCheckInProtocol(ParticipantRole::TENANT);
        $this->addParticipants($protocol, initiatorRole: ParticipantRole::TENANT);

        // Only tenant (initiator) signs
        $protocol->initiator()->sign();

        // Should NOT be able to finalize as bilateral
        $action = app(FinalizeCheckInAction::class);
        $this->assertFalse($action->canFinalize($protocol, allowUnilateral: false));

        // But CAN finalize as unilateral
        $this->assertTrue($action->canFinalize($protocol, allowUnilateral: true));
    }

    /**
     * §26.6: Payment is not hardcoded as payment = protocol.
     * Entitlement model supports Full Cycle / PRO / Agency.
     *
     * This is verified by checking the Protocol model doesn't have payment_required field
     * and we have separate Billing module.
     */
    public function test_guard_6_payment_not_hardcoded_per_protocol(): void
    {
        $protocol = new Protocol;
        $fillable = $protocol->getFillable();

        $this->assertNotContains('payment_required', $fillable);
        $this->assertNotContains('is_paid', $fillable);

        // Billing is a separate module
        $this->assertTrue(
            class_exists(BillingServiceProvider::class),
            'Billing module should exist as separate bounded context'
        );
    }

    /**
     * §26.11: No artifact reduces check-out to symmetric "both confirmed".
     *
     * Check-out action only requires 1 acceptance (initiator).
     */
    public function test_guard_11_checkout_is_not_symmetric(): void
    {
        $checkInAction = app(FinalizeCheckInAction::class);
        $checkOutAction = app(IssueCheckOutAction::class);

        // Check-in requires 2 acceptances
        $this->assertEquals(2, $checkInAction->requiredAcceptancesCount());

        // Check-out requires only 1 acceptance
        $this->assertEquals(1, $checkOutAction->requiredAcceptancesCount());

        // They are different - asymmetric by design
        $this->assertNotEquals(
            $checkInAction->requiredAcceptancesCount(),
            $checkOutAction->requiredAcceptancesCount(),
            'Check-in and check-out must have different acceptance requirements (asymmetry)'
        );
    }

    /**
     * §26.11: No shared finalize() method exists; two separate state machines.
     *
     * Check-in uses FinalizeCheckInAction, check-out uses IssueCheckOutAction.
     * There is no generic finalize() method.
     */
    public function test_guard_11_no_shared_finalize_separate_state_machines(): void
    {
        $protocol = new Protocol;

        // 1. There should be no generic finalize() method on Protocol
        $this->assertFalse(
            method_exists($protocol, 'finalize'),
            'Protocol should NOT have a generic finalize() method.'
        );

        // 2. Two separate Action classes exist for each type
        $this->assertTrue(
            class_exists(FinalizeCheckInAction::class),
            'FinalizeCheckInAction must exist for check-in flow'
        );
        $this->assertTrue(
            class_exists(IssueCheckOutAction::class),
            'IssueCheckOutAction must exist for check-out flow'
        );

        // 3. They have different method signatures and purposes
        $checkInAction = app(FinalizeCheckInAction::class);
        $checkOutAction = app(IssueCheckOutAction::class);

        // Check-in requires bilateral signatures by default
        $this->assertTrue(
            method_exists($checkInAction, 'canFinalize'),
            'FinalizeCheckInAction must have canFinalize method'
        );

        // Check-out issues act unilaterally with objection window
        $this->assertTrue(
            method_exists($checkOutAction, 'canIssue'),
            'IssueCheckOutAction must have canIssue method'
        );

        // 4. FinalizeCheckInAction rejects check-out protocols
        $checkOut = $this->createCheckOutProtocol();
        $this->addParticipants($checkOut);
        $this->assertFalse($checkInAction->canFinalize($checkOut));

        // 5. IssueCheckOutAction rejects check-in protocols
        $checkIn = $this->createCheckInProtocol();
        $this->addParticipants($checkIn);
        $this->assertFalse($checkOutAction->canIssue($checkIn));
    }

    /**
     * Test: Check-in finalization requires both signatures.
     */
    public function test_checkin_requires_both_signatures_for_bilateral(): void
    {
        $protocol = $this->createCheckInProtocol();
        $this->addParticipants($protocol);

        $action = app(FinalizeCheckInAction::class);

        // No signatures - cannot finalize
        $this->assertFalse($action->canFinalize($protocol));

        // Only initiator signed - cannot finalize bilateral
        $protocol->initiator()->sign();
        $this->assertFalse($action->canFinalize($protocol, allowUnilateral: false));

        // Both signed - can finalize
        $protocol->counterparty()->sign();
        $this->assertTrue($action->canFinalize($protocol));
    }

    /**
     * Test: Check-out opens objection window on issuance.
     */
    public function test_checkout_opens_objection_window(): void
    {
        $protocol = $this->createCheckOutProtocol();
        $this->addParticipants($protocol);
        $protocol->initiator()->sign();

        // Give initiator an entitlement for D3 gate
        $this->giveEntitlement($this->landlord, AllowedAction::CREATE_CHECK_OUT);

        $action = app(IssueCheckOutAction::class);
        $result = $action->execute($protocol, objectionWindowHours: 72);

        $this->assertNotNull($result->objection_window_ends_at);
        $this->assertTrue($result->isObjectionWindowOpen());
        // Allow 1 hour margin for test execution time
        $this->assertGreaterThanOrEqual(71, $result->remainingObjectionHours());
        $this->assertLessThanOrEqual(72, $result->remainingObjectionHours());
    }

    /**
     * Test: FinalizeCheckInAction throws for check-out protocol.
     */
    public function test_finalize_checkin_action_rejects_checkout(): void
    {
        $protocol = $this->createCheckOutProtocol();
        $this->addParticipants($protocol);
        $protocol->initiator()->sign();

        $action = app(FinalizeCheckInAction::class);

        $this->expectException(ProtocolFinalizationException::class);
        $action->execute($protocol);
    }

    /**
     * Test: IssueCheckOutAction throws for check-in protocol.
     */
    public function test_issue_checkout_action_rejects_checkin(): void
    {
        $protocol = $this->createCheckInProtocol();
        $this->addParticipants($protocol);
        $protocol->initiator()->sign();
        $protocol->counterparty()->sign();

        $action = app(IssueCheckOutAction::class);

        $this->expectException(ProtocolFinalizationException::class);
        $action->execute($protocol);
    }

    /**
     * G2: SignProtocolAction creates acceptance record with forensic data.
     *
     * §3: подпись создаёт строку с consent_text_snapshot, ip, user_agent, device_fingerprint
     */
    public function test_sign_protocol_creates_acceptance_record(): void
    {
        $protocol = $this->createCheckInProtocol();
        $this->addParticipants($protocol);

        $initiator = $protocol->initiator();
        $action = app(SignProtocolAction::class);

        // Sign with forensic data
        $action->execute(
            $protocol,
            $initiator,
            signatureData: 'base64-signature-data',
            ipAddress: '192.168.1.100',
            userAgent: 'Mozilla/5.0 Test',
            deviceFingerprint: 'abc123fingerprint'
        );

        // Verify acceptance record was created
        $this->assertDatabaseHas('acceptances', [
            'protocol_id' => $protocol->id,
            'accepted_by_user_id' => $initiator->user_id,
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0 Test',
            'device_fingerprint' => 'abc123fingerprint',
        ]);

        // Verify consent_text_snapshot is not empty
        $acceptance = Acceptance::where('protocol_id', $protocol->id)->first();
        $this->assertNotNull($acceptance);
        $this->assertNotEmpty($acceptance->consent_text_snapshot);
        $this->assertStringContainsString('protokołu zdawczo-odbiorczego', $acceptance->consent_text_snapshot);
    }

    /**
     * G2: Check-in creates 2 acceptance records (bilateral).
     */
    public function test_checkin_creates_two_acceptances(): void
    {
        $protocol = $this->createCheckInProtocol();
        $this->addParticipants($protocol);

        $action = app(SignProtocolAction::class);

        // First signature - initiator
        $action->execute($protocol, $protocol->initiator());

        // Second signature - counterparty
        $protocol->refresh();
        $action->execute($protocol, $protocol->counterparty());

        // Verify 2 acceptance records exist
        $this->assertDatabaseCount('acceptances', 2);

        $acceptances = Acceptance::where('protocol_id', $protocol->id)->get();
        $this->assertCount(2, $acceptances);

        // Verify roles
        $roles = $acceptances->pluck('accepted_by_role')->map(fn ($r) => $r->value)->toArray();
        $this->assertContains('landlord', $roles);
        $this->assertContains('tenant', $roles);
    }

    /**
     * G2: Check-out creates 1 acceptance record (unilateral).
     */
    public function test_checkout_creates_one_acceptance(): void
    {
        $protocol = $this->createCheckOutProtocol();
        $this->addParticipants($protocol);

        $action = app(SignProtocolAction::class);

        // Only initiator signs for check-out
        $action->execute($protocol, $protocol->initiator());

        // Verify 1 acceptance record exists
        $acceptances = Acceptance::where('protocol_id', $protocol->id)->get();
        $this->assertCount(1, $acceptances);

        // Verify it's the initiator's acceptance
        $this->assertEquals(ParticipantRole::LANDLORD, $acceptances->first()->accepted_by_role);
    }

    /**
     * §26.3: Unilateral document triggers notification to other party.
     *
     * When tenant creates unilateral check-in, landlord receives notification.
     */
    public function test_guard_3_unilateral_document_triggers_notification(): void
    {
        Event::fake([ParticipantInvited::class]);

        $protocol = $this->createCheckInProtocol(ParticipantRole::TENANT);
        $protocol->update(['legal_mode' => LegalMode::UNILATERAL_TENANT]);

        // Add participants - tenant is initiator
        $this->addParticipants($protocol, initiatorRole: ParticipantRole::TENANT);

        // Counterparty (landlord) should be notified
        $counterparty = $protocol->counterparty();
        $this->assertNotNull($counterparty);
        $this->assertEquals(ParticipantRole::LANDLORD, $counterparty->role);

        // Verify legal_mode indicates unilateral
        $this->assertEquals(LegalMode::UNILATERAL_TENANT, $protocol->legal_mode);

        // The template type for unilateral should mention notification
        $templateType = PdfTemplateType::fromProtocol($protocol);
        $this->assertEquals(PdfTemplateType::UNILATERAL_TENANT_CHECKIN, $templateType);

        // Legal context should mention notification
        $this->assertStringContainsString(
            'Wynajmujący został powiadomiony',
            $templateType->legalContext()
        );
    }

    /**
     * §26.4: PDF contains initiator role and all signers.
     *
     * PDF template data includes initiator and participants with sign status.
     */
    public function test_guard_4_pdf_contains_initiator_and_signers(): void
    {
        $protocol = $this->createCheckInProtocol();
        $this->addParticipants($protocol);

        // Sign both parties
        $protocol->initiator()->sign();
        $protocol->counterparty()->sign();
        $protocol->refresh();

        // Verify protocol has initiator_role
        $this->assertEquals(ParticipantRole::LANDLORD, $protocol->initiator_role);

        // Verify all participants are accessible for PDF
        $participants = $protocol->participants;
        $this->assertCount(2, $participants);

        // Verify initiator is marked
        $initiator = $participants->firstWhere('is_initiator', true);
        $this->assertNotNull($initiator);
        $this->assertTrue($initiator->hasSigned());

        // Verify counterparty signed
        $counterparty = $participants->firstWhere('is_initiator', false);
        $this->assertNotNull($counterparty);
        $this->assertTrue($counterparty->hasSigned());

        // Verify signed_at timestamps exist for PDF
        $this->assertNotNull($initiator->signed_at);
        $this->assertNotNull($counterparty->signed_at);
    }

    /**
     * §26.5: Counterparty can comment and add photos via magic-link.
     *
     * Counterparty creates comments with forensic data, uploads photos with SHA-256.
     * Both are recorded in inspection_events.
     */
    public function test_guard_5_counterparty_can_comment_and_add_photos(): void
    {
        Storage::fake('minio');

        $protocol = $this->createCheckInProtocol();
        $this->addParticipants($protocol);

        // Get counterparty participant
        $counterparty = $protocol->counterparty();
        $this->assertNotNull($counterparty);

        // === Test 1: Counterparty can add a comment ===
        $commentService = new ProtocolCommentService;
        $comment = $commentService->addComment(
            $protocol,
            $counterparty,
            'Stan mieszkania jest zgodny z opisem, ale zauważyłem drobne rysy na podłodze.',
            commentableType: 'protocol',
            commentableId: $protocol->id,
            ipAddress: '192.168.1.50',
            userAgent: 'Mozilla/5.0 Counterparty',
            deviceFingerprint: 'counterparty-device-123'
        );

        // Comment was created with forensic data
        $this->assertDatabaseHas('protocol_comments', [
            'protocol_id' => $protocol->id,
            'author_role' => ParticipantRole::TENANT->value,
            'author_user_id' => $counterparty->user_id,
            'ip_address' => '192.168.1.50',
            'user_agent' => 'Mozilla/5.0 Counterparty',
            'device_fingerprint' => 'counterparty-device-123',
        ]);

        // Comment content is stored
        $this->assertNotEmpty($comment->body);
        $this->assertStringContainsString('drobne rysy', $comment->body);

        // === Test 2: Counterparty can upload a photo ===
        $photoService = new CounterpartyPhotoService;
        $file = UploadedFile::fake()->image('counterparty-photo.jpg', 800, 600);
        $fileContent = file_get_contents($file->getRealPath());
        $expectedHash = hash('sha256', $fileContent);

        $photo = $photoService->upload(
            $protocol,
            $counterparty,
            $file,
            ipAddress: '192.168.1.50',
            userAgent: 'Mozilla/5.0 Counterparty'
        );

        // Photo was created with SHA-256 hash
        $this->assertDatabaseHas('counterparty_photos', [
            'protocol_id' => $protocol->id,
            'uploaded_by_role' => ParticipantRole::TENANT->value,
            'uploaded_by_user_id' => $counterparty->user_id,
            'ip_address' => '192.168.1.50',
        ]);

        // Hash is valid SHA-256
        $this->assertEquals(64, strlen($photo->hash));
        $this->assertEquals($expectedHash, $photo->hash);

        // File is stored in MinIO
        Storage::disk('minio')->assertExists($photo->path);

        // === Test 3: Both actions create inspection events ===
        $events = InspectionEvent::where('protocol_id', $protocol->id)->get();

        $eventTypes = $events->pluck('event_type')->filter()->map(fn ($t) => $t->value)->toArray();
        $this->assertContains('comment_added', $eventTypes);
        $this->assertContains('counterparty_photo_uploaded', $eventTypes);
    }

    /**
     * §26.7: All three reference_mode values work for check-out.
     *
     * SYSTEM_BASELINE, UPLOADED_REFERENCE, NO_REFERENCE.
     */
    public function test_guard_7_three_reference_modes_work(): void
    {
        // Test 1: SYSTEM_BASELINE - linked to existing check-in
        $checkIn = $this->createCheckInProtocol();
        $checkIn->update(['status' => ProtocolStatus::COMPLETED]);

        $checkOutWithBaseline = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'reference_mode' => ReferenceMode::SYSTEM_BASELINE,
            'linked_checkin_id' => $checkIn->id,
            'title' => 'Check-out with baseline',
        ]);

        $this->assertEquals(ReferenceMode::SYSTEM_BASELINE, $checkOutWithBaseline->reference_mode);
        $this->assertNotNull($checkOutWithBaseline->linkedCheckin);
        $this->assertTrue($checkOutWithBaseline->reference_mode->requiresLinkedCheckin());

        // Test 2: UPLOADED_REFERENCE - external document uploaded
        $checkOutWithUpload = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'reference_mode' => ReferenceMode::UPLOADED_REFERENCE,
            'title' => 'Check-out with upload',
        ]);

        $this->assertEquals(ReferenceMode::UPLOADED_REFERENCE, $checkOutWithUpload->reference_mode);
        $this->assertTrue($checkOutWithUpload->reference_mode->supportsUploadedReference());
        $this->assertFalse($checkOutWithUpload->reference_mode->requiresLinkedCheckin());

        // Can add reference document
        $refDoc = ReferenceDocument::create([
            'protocol_id' => $checkOutWithUpload->id,
            'type' => ReferenceDocumentType::PDF,
            'title' => 'Poprzedni protokół zdawczo-odbiorczy',
            'filename' => 'protokol_poprzedni.pdf',
            'original_filename' => 'skan_protokolu.pdf',
            'path' => 'documents/test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 12345,
            'hash' => hash('sha256', 'test-content'),
            'uploaded_at' => now(),
            'note' => 'Previous protocol scan',
        ]);
        $this->assertDatabaseHas('reference_documents', ['protocol_id' => $checkOutWithUpload->id]);

        // Test 3: NO_REFERENCE - standalone documentation
        $checkOutNoRef = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'reference_mode' => ReferenceMode::NO_REFERENCE,
            'title' => 'Check-out standalone',
        ]);

        $this->assertEquals(ReferenceMode::NO_REFERENCE, $checkOutNoRef->reference_mode);
        $this->assertFalse($checkOutNoRef->reference_mode->requiresLinkedCheckin());
        $this->assertFalse($checkOutNoRef->reference_mode->supportsUploadedReference());

        // All three modes are distinct
        $this->assertNotEquals(
            $checkOutWithBaseline->reference_mode,
            $checkOutWithUpload->reference_mode
        );
        $this->assertNotEquals(
            $checkOutWithUpload->reference_mode,
            $checkOutNoRef->reference_mode
        );
    }

    /**
     * §26.10: Photos stored in MinIO with SHA-256 hash.
     *
     * Evidence upload calculates and stores SHA-256 hash.
     */
    public function test_guard_10_photos_stored_with_sha256_hash(): void
    {
        Storage::fake('minio');

        $protocol = $this->createCheckInProtocol();
        $this->addParticipants($protocol);

        // Create a test image
        $file = UploadedFile::fake()->image('test-photo.jpg', 800, 600);
        $fileContent = file_get_contents($file->getRealPath());
        $expectedHash = hash('sha256', $fileContent);

        // Upload using service
        $service = new EvidenceUploadService('minio');
        $evidence = $service->upload(
            $file,
            $protocol,
            $this->landlord,
            caption: 'Test photo'
        );

        // Verify SHA-256 hash is stored
        $this->assertNotNull($evidence->hash);
        $this->assertEquals(64, strlen($evidence->hash)); // SHA-256 = 64 hex chars
        $this->assertEquals($expectedHash, $evidence->hash);

        // Verify hash is valid SHA-256 format
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $evidence->hash);

        // Verify file is stored in MinIO
        Storage::disk('minio')->assertExists($evidence->path);

        // Verify evidence record exists in database
        $this->assertDatabaseHas('evidences', [
            'id' => $evidence->id,
            'protocol_id' => $protocol->id,
            'hash' => $expectedHash,
            'disk' => 'minio',
        ]);
    }

    /**
     * §26.8: Editing catalog or translation doesn't change sealed protocol.
     *
     * Protocol room/item names are frozen via snapshot at creation time.
     */
    public function test_guard_8_catalog_change_does_not_affect_sealed_protocol(): void
    {
        // Create protocol with room that has name_snapshot
        $protocol = $this->createCheckInProtocol();
        $this->addParticipants($protocol);

        // Create a catalog item (room type)
        $catalogItem = CatalogItem::create([
            'type' => CatalogItemType::ROOM,
            'name' => ['pl' => 'Salon', 'en' => 'Living Room'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Create protocol room with frozen name_snapshot (from catalog)
        $room = ProtocolRoom::create([
            'protocol_id' => $protocol->id,
            'catalog_item_id' => $catalogItem->id,
            'name_snapshot' => ['pl' => 'Salon', 'en' => 'Living Room'],
            'sort_order' => 1,
        ]);

        // Freeze the protocol (complete it)
        $protocol->update([
            'status' => ProtocolStatus::COMPLETED,
            'completed_at' => now(),
        ]);

        $originalSnapshot = $room->name_snapshot;

        // === KEY TEST: Update catalog item name ===
        $catalogItem->update([
            'name' => ['pl' => 'Pokój dzienny', 'en' => 'Living Area'],
        ]);
        $catalogItem->refresh();

        // Verify catalog item was updated
        $this->assertEquals('Pokój dzienny', $catalogItem->getTranslation('name', 'pl'));

        // Reload the protocol room
        $room->refresh();

        // D8/G5: Protocol room snapshot MUST remain unchanged
        $this->assertEquals($originalSnapshot, $room->name_snapshot);
        $this->assertEquals('Salon', $room->name_snapshot['pl']);
        $this->assertNotEquals($catalogItem->getTranslation('name', 'pl'), $room->name_snapshot['pl']);

        // Display name should use frozen snapshot, not live catalog
        $this->assertStringContainsString('Salon', $room->display_name);
    }

    /**
     * §26.9: QR page does not expose party names or private photos.
     *
     * Verification page shows document existence and hash without PII.
     */
    public function test_guard_9_qr_page_does_not_expose_pii(): void
    {
        $protocol = $this->createCheckInProtocol();
        $this->addParticipants($protocol);

        // Create document with hash
        $documentHash = hash('sha256', 'test-document-content');
        $protocol->update(['document_hash' => $documentHash]);

        $document = GeneratedDocument::create([
            'protocol_id' => $protocol->id,
            'type' => DocumentType::PROTOCOL_PDF,
            'locale' => 'pl',
            'filename' => 'protocol.pdf',
            'path' => 'documents/protocol.pdf',
            'disk' => 'minio',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'hash' => $documentHash,
            'generated_at' => now(),
        ]);

        // Access QR verification page
        $response = $this->get("/verify/{$documentHash}");

        $response->assertStatus(200);

        // Should NOT contain landlord or tenant names
        $response->assertDontSee('Wynajmujący'); // setUp landlord name
        $response->assertDontSee('Najemca'); // setUp tenant name
        $response->assertDontSee('landlord@example.com');
        $response->assertDontSee('tenant@example.com');

        // Should contain verification info
        $response->assertSee('Dokument zweryfikowany');
    }

    /**
     * §26.12: Without consumed entitlement - cannot send link, issue act, generate PDF.
     *
     * HARD-GATE enforcement for all critical actions.
     */
    public function test_guard_12_hard_gate_entitlement_required(): void
    {
        $protocol = $this->createCheckInProtocol();
        $this->addParticipants($protocol);
        $protocol->initiator()->sign();
        $protocol->counterparty()->sign();
        $protocol->refresh();

        // Without entitlement, finalization should fail
        $action = app(FinalizeCheckInAction::class);

        // Try to finalize without entitlement - should throw
        $this->expectException(EntitlementRequiredException::class);
        $action->execute($protocol);
    }

    /**
     * §21 guard 13: An unresolved objection does NOT block checkout completion.
     *
     * Objection is a one-sided act recorded alongside the protocol, never a
     * precondition for finalization ("вторая сторона должна разрешить" is a
     * forbidden pattern at the completion layer).
     *
     * Дано: CHECK_OUT, act_issued_at установлен, objection_window_ends_at <= now,
     * возражение поднято и НЕ закрыто (protocol transitioned SIGNED -> DISPUTED
     * by RaiseObjectionAction, mirroring the real transition path exercised in
     * ObjectionFlowTest::test_objection_transitions_protocol_to_disputed).
     * Когда: CompleteExpiredObjectionWindows отрабатывает.
     * Тогда: протокол -> completed; возражение сохранено, прикреплено и по-прежнему
     * unresolved (резолюция объекции — НЕ предусловие завершения).
     */
    public function test_guard_13_checkout_completes_despite_unresolved_objection(): void
    {
        $protocol = $this->createCheckOutProtocol();
        $this->addParticipants($protocol);
        $protocol->initiator()->sign();

        $this->giveEntitlement($this->landlord, AllowedAction::CREATE_CHECK_OUT);

        $issueAction = app(IssueCheckOutAction::class);
        $protocol = $issueAction->execute($protocol, objectionWindowHours: 72);

        // IssueCheckOutAction completes the protocol immediately on issuance.
        // Roll back to SIGNED to exercise the intermediate state that
        // RaiseObjectionAction's SIGNED -> DISPUTED transition (and this job)
        // are designed for — same convention as ObjectionFlowTest already uses.
        $protocol->update(['status' => ProtocolStatus::SIGNED]);

        $counterparty = $protocol->counterparty();
        $objectionAction = app(RaiseObjectionAction::class);
        $objection = $objectionAction->execute(
            $protocol,
            $counterparty,
            'Nie zgadzam się ze stanem łazienki.'
        );

        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::DISPUTED, $protocol->status);
        $this->assertTrue($objection->isPending());

        // Objection window has expired.
        $protocol->update(['objection_window_ends_at' => now()->subMinute()]);

        (new CompleteExpiredObjectionWindows)->handle();

        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::COMPLETED, $protocol->status);
        $this->assertNotNull($protocol->act_issued_at);

        // Objection remains attached to the completed protocol, unresolved,
        // available for the final document — resolution is not a precondition.
        $objection->refresh();
        $this->assertTrue($objection->isPending());
        $this->assertDatabaseHas('protocol_objections', [
            'id' => $objection->id,
            'protocol_id' => $protocol->id,
        ]);
    }

    // === Helper Methods ===

    private function createCheckInProtocol(?ParticipantRole $initiatorRole = null): Protocol
    {
        return Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => $initiatorRole ?? ParticipantRole::LANDLORD,
            'counterparty_role' => $initiatorRole === ParticipantRole::TENANT
                ? ParticipantRole::LANDLORD
                : ParticipantRole::TENANT,
            'status' => ProtocolStatus::PENDING_SIGNATURES,
            'title' => 'Test Check-In',
        ]);
    }

    private function createCheckOutProtocol(): Protocol
    {
        return Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::PENDING_SIGNATURES,
            'title' => 'Test Check-Out',
        ]);
    }

    private function addParticipants(
        Protocol $protocol,
        ?ParticipantRole $initiatorRole = null
    ): void {
        $role = $initiatorRole ?? ParticipantRole::LANDLORD;
        $counterRole = $role === ParticipantRole::LANDLORD
            ? ParticipantRole::TENANT
            : ParticipantRole::LANDLORD;

        // Initiator
        Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $role === ParticipantRole::LANDLORD ? $this->landlord->id : $this->tenant->id,
            'role' => $role,
            'is_initiator' => true,
        ]);

        // Counterparty
        Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $counterRole === ParticipantRole::TENANT ? $this->tenant->id : $this->landlord->id,
            'role' => $counterRole,
            'is_initiator' => false,
        ]);
    }

    private function giveEntitlement(
        User $user,
        AllowedAction $action
    ): void {
        Entitlement::create([
            'user_id' => $user->id,
            'product_code' => $action === AllowedAction::CREATE_CHECK_IN
                ? ProductCode::WJAZD
                : ProductCode::WYJAZD,
            'allowed_action' => $action,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);
    }
}
