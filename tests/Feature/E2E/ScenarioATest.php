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
use App\Modules\Protocol\Application\Actions\FinalizeCheckInAction;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Domain\Exceptions\ProtocolFinalizationException;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2E Scenario A: Bilateral Check-in (Landlord Initiator)
 *
 * §25 G7: Full flow test
 * 1. Landlord creates property
 * 2. Landlord creates check-in protocol
 * 3. Landlord invites tenant via magic-link
 * 4. Tenant accepts invitation via magic-link
 * 5. Both parties sign the protocol
 * 6. Protocol completes as BILATERAL_COMPLETED
 * 7. Timeline events are recorded
 * 8. Acceptances are recorded with forensic data
 */
class ScenarioATest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Property $property;

    private InvitationService $invitationService;

    private SignProtocolAction $signAction;

    private FinalizeCheckInAction $finalizeAction;

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

        // Give landlord entitlement for check-in
        Entitlement::create([
            'user_id' => $this->landlord->id,
            'product_code' => ProductCode::WJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_IN,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        $this->invitationService = app(InvitationService::class);
        $this->signAction = app(SignProtocolAction::class);
        $this->finalizeAction = app(FinalizeCheckInAction::class);
    }

    /**
     * Full E2E Scenario A: Bilateral check-in with landlord as initiator.
     */
    public function test_scenario_a_bilateral_checkin_landlord_initiator_full_flow(): void
    {
        // Step 1: Landlord creates check-in protocol
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Protokół zdawczo-odbiorczy - ul. Testowa 15/3A',
            'locale' => 'pl',
        ]);

        $this->assertDatabaseHas('protocols', [
            'id' => $protocol->id,
            'type' => 'check_in',
            'initiator_role' => 'landlord',
            'status' => 'draft',
        ]);

        // Step 2: Add landlord as initiator participant
        $landlordParticipant = $this->invitationService->addInitiator(
            $protocol,
            $this->landlord,
            ParticipantRole::LANDLORD
        );

        $this->assertTrue($landlordParticipant->isInitiator());
        $this->assertTrue($landlordParticipant->isAccepted());

        // Step 3: Landlord invites tenant via magic-link
        $invitationToken = $this->invitationService->inviteByEmail(
            $protocol,
            $this->tenant->email,
            ParticipantRole::TENANT,
            false,
            72,
            '192.168.1.100',
            'Mozilla/5.0'
        );

        $this->assertNotNull($invitationToken);
        $this->assertNotNull($invitationToken->raw_token);

        // Verify invitation event was recorded
        $invitationEvent = InspectionEvent::where('protocol_id', $protocol->id)
            ->where('event_type', InspectionEventType::INVITATION_SENT)
            ->first();
        $this->assertNotNull($invitationEvent);

        // Step 4: Tenant accepts invitation via magic-link
        $tenantParticipant = $this->invitationService->acceptInvitation(
            $invitationToken->raw_token,
            $this->tenant,
            '192.168.1.200',
            'Mozilla/5.0 Tenant Browser'
        );

        $this->assertTrue($tenantParticipant->isAccepted());
        $this->assertEquals($this->tenant->id, $tenantParticipant->user_id);

        // Verify magic link used event
        $magicLinkEvent = InspectionEvent::where('protocol_id', $protocol->id)
            ->where('event_type', InspectionEventType::MAGIC_LINK_USED)
            ->first();
        $this->assertNotNull($magicLinkEvent);

        // Step 5: Transition protocol to pending_signatures (via pending_counterparty)
        $protocol->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
        $protocol->transitionTo(ProtocolStatus::PENDING_SIGNATURES);

        // Step 6: Landlord signs
        $protocol = $this->signAction->execute(
            $protocol,
            $landlordParticipant,
            'landlord-signature-data',
            '192.168.1.100',
            'Mozilla/5.0',
            'landlord-fingerprint-abc123'
        );

        // Verify landlord signature event
        $landlordSignEvent = InspectionEvent::where('protocol_id', $protocol->id)
            ->where('event_type', InspectionEventType::SIGNATURE_ADDED)
            ->where('actor_role', 'landlord')
            ->first();
        $this->assertNotNull($landlordSignEvent);

        // Verify landlord acceptance record (G2)
        $landlordAcceptance = Acceptance::where('protocol_id', $protocol->id)
            ->where('accepted_by_role', ParticipantRole::LANDLORD)
            ->first();
        $this->assertNotNull($landlordAcceptance);
        $this->assertNotNull($landlordAcceptance->consent_text_snapshot);
        $this->assertEquals('192.168.1.100', $landlordAcceptance->ip_address);
        $this->assertEquals('landlord-fingerprint-abc123', $landlordAcceptance->device_fingerprint);

        // Refresh tenant participant
        $tenantParticipant = $tenantParticipant->fresh();

        // Step 7: Tenant signs
        $protocol = $this->signAction->execute(
            $protocol,
            $tenantParticipant,
            'tenant-signature-data',
            '192.168.1.200',
            'Mozilla/5.0 Tenant Browser',
            'tenant-fingerprint-xyz789'
        );

        // Verify tenant signature event
        $tenantSignEvent = InspectionEvent::where('protocol_id', $protocol->id)
            ->where('event_type', InspectionEventType::SIGNATURE_ADDED)
            ->where('actor_role', 'tenant')
            ->first();
        $this->assertNotNull($tenantSignEvent);

        // Verify tenant acceptance record (G2)
        $tenantAcceptance = Acceptance::where('protocol_id', $protocol->id)
            ->where('accepted_by_role', ParticipantRole::TENANT)
            ->first();
        $this->assertNotNull($tenantAcceptance);
        $this->assertNotNull($tenantAcceptance->consent_text_snapshot);
        $this->assertEquals('tenant-fingerprint-xyz789', $tenantAcceptance->device_fingerprint);

        // Step 8: Verify protocol is now signed (both parties signed)
        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::SIGNED, $protocol->status);
        $this->assertTrue($protocol->allParticipantsSigned());

        // Step 9: Finalize protocol
        $protocol = $this->finalizeAction->execute(
            $protocol,
            false, // bilateral, not unilateral
            '192.168.1.100',
            'Mozilla/5.0'
        );

        // Step 10: Verify final state
        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::COMPLETED, $protocol->status);
        $this->assertNotNull($protocol->completed_at);

        // Verify timeline has complete event chain
        $events = InspectionEvent::where('protocol_id', $protocol->id)
            ->orderBy('created_at')
            ->get();

        $eventTypes = $events->pluck('event_type')->filter()->map(fn ($t) => $t->value)->toArray();

        $this->assertContains('invitation_sent', $eventTypes);
        $this->assertContains('magic_link_used', $eventTypes);
        $this->assertContains('signature_added', $eventTypes);
        $this->assertContains('protocol_finalized', $eventTypes);

        // Verify 2 acceptances exist (bilateral)
        $acceptanceCount = Acceptance::where('protocol_id', $protocol->id)->count();
        $this->assertEquals(2, $acceptanceCount);
    }

    /**
     * Test that both parties must sign for bilateral check-in.
     */
    public function test_scenario_a_requires_both_signatures_for_bilateral(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Test bilateral',
        ]);

        // Add participants
        $landlordParticipant = Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
            'invited_at' => now(),
            'accepted_at' => now(),
            'signed_at' => now(), // Landlord signed
        ]);

        $tenantParticipant = Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $this->tenant->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
            'invited_at' => now(),
            'accepted_at' => now(),
            // Not signed yet
        ]);

        $protocol->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
        $protocol->transitionTo(ProtocolStatus::PENDING_SIGNATURES);

        // With only landlord signed, cannot finalize as bilateral
        $this->expectException(ProtocolFinalizationException::class);
        $this->finalizeAction->execute($protocol, false);
    }

    /**
     * Test timeline events are properly ordered.
     */
    public function test_scenario_a_timeline_events_are_chronologically_ordered(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Timeline test',
        ]);

        // Record events with known order
        InspectionEvent::record($protocol, InspectionEventType::PROTOCOL_CREATED, 'landlord', $this->landlord->id);
        sleep(1); // Ensure timestamp difference
        InspectionEvent::record($protocol, InspectionEventType::INVITATION_SENT, 'landlord', $this->landlord->id);
        sleep(1);
        InspectionEvent::record($protocol, InspectionEventType::MAGIC_LINK_USED, 'tenant', $this->tenant->id);

        $events = InspectionEvent::where('protocol_id', $protocol->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $this->assertEquals(3, $events->count());
        $this->assertEquals(InspectionEventType::PROTOCOL_CREATED, $events[0]->event_type);
        $this->assertEquals(InspectionEventType::INVITATION_SENT, $events[1]->event_type);
        $this->assertEquals(InspectionEventType::MAGIC_LINK_USED, $events[2]->event_type);
    }

    /**
     * Test acceptance records contain all forensic data.
     */
    public function test_scenario_a_acceptances_contain_forensic_data(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::PENDING_SIGNATURES,
            'title' => 'Forensic test',
        ]);

        $participant = Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->signAction->execute(
            $protocol,
            $participant,
            'test-signature',
            '10.0.0.1',
            'Test Browser 1.0',
            'fingerprint-test-123'
        );

        $acceptance = Acceptance::where('protocol_id', $protocol->id)->first();

        $this->assertNotNull($acceptance);
        $this->assertNotNull($acceptance->consent_text_snapshot);
        $this->assertStringContainsString('protokołu zdawczo-odbiorczego', $acceptance->consent_text_snapshot);
        $this->assertEquals('10.0.0.1', $acceptance->ip_address);
        $this->assertEquals('Test Browser 1.0', $acceptance->user_agent);
        $this->assertEquals('fingerprint-test-123', $acceptance->device_fingerprint);
        $this->assertNotNull($acceptance->accepted_at);
    }
}
