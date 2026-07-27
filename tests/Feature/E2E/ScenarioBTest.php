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
 * E2E Scenario B: Bilateral Check-in (Tenant Initiator)
 *
 * §25 G7 / §26.2: Tenant-initiated check-in flow
 * 1. Tenant creates property (tenant_declared)
 * 2. Tenant creates check-in protocol
 * 3. Tenant invites landlord via magic-link
 * 4. Landlord accepts invitation
 * 5. Both parties sign
 * 6. Protocol completes as BILATERAL_COMPLETED
 *
 * Key asymmetry test: Check-in from tenant without landlord is NOT bilateral (§26.2)
 */
class ScenarioBTest extends TestCase
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

        $this->tenant = User::create([
            'name' => 'Anna Najemca',
            'email' => 'tenant@example.com',
            'password' => 'password123',
        ]);

        $this->landlord = User::create([
            'name' => 'Jan Wynajmujący',
            'email' => 'landlord@example.com',
            'password' => 'password123',
        ]);

        // Tenant-declared property (tenant is initiator)
        $this->property = Property::create([
            'user_id' => $this->tenant->id,
            'name' => 'Wynajmowane mieszkanie',
            'street' => 'ul. Najemcy',
            'building_number' => '20',
            'apartment_number' => '5B',
            'city' => 'Kraków',
            'postal_code' => '30-001',
            'country' => 'PL',
            'declaration_type' => DeclarationType::TENANT_DECLARED,
        ]);

        // Give tenant entitlement for check-in
        Entitlement::create([
            'user_id' => $this->tenant->id,
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
     * Full E2E Scenario B: Bilateral check-in with tenant as initiator.
     */
    public function test_scenario_b_bilateral_checkin_tenant_initiator_full_flow(): void
    {
        // Step 1: Tenant creates check-in protocol
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->tenant->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::TENANT,
            'counterparty_role' => ParticipantRole::LANDLORD,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Protokół zdawczo-odbiorczy - ul. Najemcy 20/5B',
            'locale' => 'pl',
        ]);

        $this->assertDatabaseHas('protocols', [
            'id' => $protocol->id,
            'type' => 'check_in',
            'initiator_role' => 'tenant',
            'counterparty_role' => 'landlord',
        ]);

        // Verify property is tenant_declared
        $this->assertEquals(DeclarationType::TENANT_DECLARED, $this->property->declaration_type);

        // Step 2: Add tenant as initiator
        $tenantParticipant = $this->invitationService->addInitiator(
            $protocol,
            $this->tenant,
            ParticipantRole::TENANT
        );

        $this->assertTrue($tenantParticipant->isInitiator());
        $this->assertEquals(ParticipantRole::TENANT, $tenantParticipant->role);

        // Step 3: Tenant invites landlord via magic-link
        $invitationToken = $this->invitationService->inviteByEmail(
            $protocol,
            $this->landlord->email,
            ParticipantRole::LANDLORD,
            false,
            72,
            '192.168.1.50',
            'Tenant Browser'
        );

        $this->assertNotNull($invitationToken);

        // Verify invitation event
        $this->assertDatabaseHas('inspection_events', [
            'protocol_id' => $protocol->id,
            'event_type' => InspectionEventType::INVITATION_SENT->value,
        ]);

        // Step 4: Landlord accepts invitation
        $landlordParticipant = $this->invitationService->acceptInvitation(
            $invitationToken->raw_token,
            $this->landlord,
            '192.168.1.60',
            'Landlord Browser'
        );

        $this->assertTrue($landlordParticipant->isAccepted());
        $this->assertFalse($landlordParticipant->isInitiator());
        $this->assertEquals(ParticipantRole::LANDLORD, $landlordParticipant->role);

        // Step 5: Transition to pending_signatures (via pending_counterparty)
        $protocol->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
        $protocol->transitionTo(ProtocolStatus::PENDING_SIGNATURES);

        // Step 6: Tenant (initiator) signs first
        $protocol = $this->signAction->execute(
            $protocol,
            $tenantParticipant,
            'tenant-signature',
            '192.168.1.50',
            'Tenant Browser',
            'tenant-fp'
        );

        // Protocol should still be pending_signatures (need landlord too)
        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::PENDING_SIGNATURES, $protocol->status);

        // Step 7: Landlord signs
        $landlordParticipant = $landlordParticipant->fresh();
        $protocol = $this->signAction->execute(
            $protocol,
            $landlordParticipant,
            'landlord-signature',
            '192.168.1.60',
            'Landlord Browser',
            'landlord-fp'
        );

        // Step 8: Protocol is now signed (both parties)
        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::SIGNED, $protocol->status);
        $this->assertTrue($protocol->allParticipantsSigned());

        // Step 9: Finalize
        $protocol = $this->finalizeAction->execute($protocol, false);

        $this->assertEquals(ProtocolStatus::COMPLETED, $protocol->status);

        // Verify 2 acceptances (bilateral)
        $acceptances = Acceptance::where('protocol_id', $protocol->id)->get();
        $this->assertEquals(2, $acceptances->count());

        $roles = $acceptances->pluck('accepted_by_role')->map(fn ($r) => $r->value)->toArray();
        $this->assertContains('tenant', $roles);
        $this->assertContains('landlord', $roles);
    }

    /**
     * §26.2: Check-in from tenant without landlord signature is NOT bilateral.
     */
    public function test_scenario_b_guard_2_tenant_checkin_without_landlord_not_bilateral(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->tenant->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::TENANT,
            'counterparty_role' => ParticipantRole::LANDLORD,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Unilateral test',
        ]);

        // Add tenant as initiator
        $tenantParticipant = Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $this->tenant->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => true,
            'invited_at' => now(),
            'accepted_at' => now(),
            'signed_at' => now(), // Tenant signed
        ]);

        // Add landlord but NOT signed
        $landlordParticipant = Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => false,
            'invited_at' => now(),
            'accepted_at' => now(),
            // NOT signed
        ]);

        $protocol->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
        $protocol->transitionTo(ProtocolStatus::PENDING_SIGNATURES);

        // Cannot finalize as bilateral without landlord signature
        $this->expectException(ProtocolFinalizationException::class);
        $this->expectExceptionMessage('Counterparty must sign for bilateral check-in');

        $this->finalizeAction->execute($protocol, false);
    }

    /**
     * §26.2: Allow unilateral finalization when explicitly requested.
     */
    public function test_scenario_b_can_finalize_unilateral_when_allowed(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->tenant->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::TENANT,
            'counterparty_role' => ParticipantRole::LANDLORD,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Unilateral allowed test',
        ]);

        // Add tenant as initiator (signed)
        Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $this->tenant->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => true,
            'invited_at' => now(),
            'accepted_at' => now(),
            'signed_at' => now(),
        ]);

        // Add landlord but NOT signed
        Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => false,
            'invited_at' => now(),
            // Not accepted, not signed
        ]);

        $protocol->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
        $protocol->transitionTo(ProtocolStatus::PENDING_SIGNATURES);

        // Finalize with allowUnilateral = true
        $protocol = $this->finalizeAction->execute($protocol, true);

        $this->assertEquals(ProtocolStatus::COMPLETED, $protocol->status);

        // Check timeline records unilateral flag
        $finalizeEvent = InspectionEvent::where('protocol_id', $protocol->id)
            ->where('event_type', InspectionEventType::PROTOCOL_FINALIZED)
            ->first();

        $this->assertNotNull($finalizeEvent);
        $this->assertTrue($finalizeEvent->payload['unilateral'] ?? false);
    }

    /**
     * Test roles are correctly assigned when tenant is initiator.
     */
    public function test_scenario_b_roles_correctly_assigned(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->tenant->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::TENANT,
            'counterparty_role' => ParticipantRole::LANDLORD,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Role test',
        ]);

        // Add participants
        $tenantParticipant = $this->invitationService->addInitiator(
            $protocol,
            $this->tenant,
            ParticipantRole::TENANT
        );

        $invitationToken = $this->invitationService->inviteByEmail(
            $protocol,
            $this->landlord->email,
            ParticipantRole::LANDLORD
        );

        $landlordParticipant = $this->invitationService->acceptInvitation(
            $invitationToken->raw_token,
            $this->landlord
        );

        // Verify initiator
        $this->assertEquals($tenantParticipant->id, $protocol->initiator()->id);
        $this->assertEquals(ParticipantRole::TENANT, $protocol->initiator()->role);

        // Verify counterparty
        $this->assertEquals($landlordParticipant->id, $protocol->counterparty()->id);
        $this->assertEquals(ParticipantRole::LANDLORD, $protocol->counterparty()->role);

        // Verify role-based queries
        $this->assertEquals($landlordParticipant->id, $protocol->landlord()->id);
        $this->assertEquals($tenantParticipant->id, $protocol->tenant()->id);
    }

    /**
     * Test participant status spectrum for tenant-initiated flow.
     */
    public function test_scenario_b_participant_status_spectrum(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->tenant->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::TENANT,
            'counterparty_role' => ParticipantRole::LANDLORD,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Status spectrum test',
        ]);

        // Add tenant initiator - should be 'accepted' since addInitiator auto-accepts
        $tenantParticipant = $this->invitationService->addInitiator(
            $protocol,
            $this->tenant,
            ParticipantRole::TENANT
        );
        $this->assertEquals('accepted', $tenantParticipant->participation_status);

        // Invite landlord - status should be 'sent'
        $invitationToken = $this->invitationService->inviteByEmail(
            $protocol,
            $this->landlord->email,
            ParticipantRole::LANDLORD
        );

        $landlordParticipant = Participant::where('protocol_id', $protocol->id)
            ->where('role', ParticipantRole::LANDLORD)
            ->first();

        $this->assertEquals('sent', $landlordParticipant->participation_status);
        $this->assertEquals('Wysłano', $landlordParticipant->participation_status_label);

        // Landlord accepts - status should progress
        $landlordParticipant = $this->invitationService->acceptInvitation(
            $invitationToken->raw_token,
            $this->landlord
        );

        $this->assertEquals('accepted', $landlordParticipant->participation_status);
        $this->assertEquals('Zaakceptowano', $landlordParticipant->participation_status_label);

        // Sign the landlord
        $landlordParticipant->sign('signature');
        $this->assertEquals('signed', $landlordParticipant->participation_status);
        $this->assertEquals('Podpisano', $landlordParticipant->participation_status_label);
    }
}
