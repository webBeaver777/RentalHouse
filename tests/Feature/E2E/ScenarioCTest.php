<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Modules\Acceptance\Application\Actions\RaiseObjectionAction;
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
use App\Modules\Protocol\Domain\Enums\ReferenceMode;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2E Scenario C: Check-out with Baseline (System Reference)
 *
 * §25 G7 / §26.1 / §26.11: Check-out with linked check-in as baseline
 * 1. Landlord completes a bilateral check-in (baseline)
 * 2. Later, landlord creates check-out linked to that check-in
 * 3. Landlord signs (unilateral - counterparty not required)
 * 4. Act is issued with objection window (72h default)
 * 5. Tenant can raise objection within window
 * 6. Objection does not block issuance
 */
class ScenarioCTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Property $property;

    private Protocol $baselineCheckIn;

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

        // Create entitlements
        Entitlement::create([
            'user_id' => $this->landlord->id,
            'product_code' => ProductCode::WJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_IN,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        Entitlement::create([
            'user_id' => $this->landlord->id,
            'product_code' => ProductCode::WYJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_OUT,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        // Create baseline check-in (completed bilateral)
        $this->baselineCheckIn = $this->createCompletedCheckIn();

        $this->invitationService = app(InvitationService::class);
        $this->signAction = app(SignProtocolAction::class);
        $this->issueAction = app(IssueCheckOutAction::class);
    }

    /**
     * Create a completed bilateral check-in as baseline.
     */
    private function createCompletedCheckIn(): Protocol
    {
        $checkIn = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::COMPLETED,
            'title' => 'Baseline Check-in',
            'completed_at' => now()->subMonths(6),
            'paid_at' => now()->subMonths(6),
        ]);

        // Add participants
        Participant::create([
            'protocol_id' => $checkIn->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
            'invited_at' => now()->subMonths(6),
            'accepted_at' => now()->subMonths(6),
            'signed_at' => now()->subMonths(6),
        ]);

        Participant::create([
            'protocol_id' => $checkIn->id,
            'user_id' => $this->tenant->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
            'invited_at' => now()->subMonths(6),
            'accepted_at' => now()->subMonths(6),
            'signed_at' => now()->subMonths(6),
        ]);

        return $checkIn;
    }

    /**
     * Full E2E Scenario C: Check-out with system baseline.
     */
    public function test_scenario_c_checkout_with_baseline_full_flow(): void
    {
        // Step 1: Landlord creates check-out linked to baseline check-in
        $checkOut = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Check-out - ul. Testowa 15/3A',
            'reference_mode' => ReferenceMode::SYSTEM_BASELINE,
            'linked_checkin_id' => $this->baselineCheckIn->id,
            'deposit_amount' => 3000.00,
            'locale' => 'pl',
        ]);

        $this->assertDatabaseHas('protocols', [
            'id' => $checkOut->id,
            'type' => 'check_out',
            'reference_mode' => 'system_baseline',
            'linked_checkin_id' => $this->baselineCheckIn->id,
        ]);

        // Verify linked check-in relationship
        $this->assertEquals($this->baselineCheckIn->id, $checkOut->linkedCheckin->id);

        // Step 2: Add landlord as initiator
        $landlordParticipant = $this->invitationService->addInitiator(
            $checkOut,
            $this->landlord,
            ParticipantRole::LANDLORD
        );

        // Step 3: Invite tenant (for notification, not blocking)
        $invitationToken = $this->invitationService->inviteByEmail(
            $checkOut,
            $this->tenant->email,
            ParticipantRole::TENANT
        );

        // Step 4: Transition to pending_signatures (via pending_counterparty)
        $checkOut->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
        $checkOut->transitionTo(ProtocolStatus::PENDING_SIGNATURES);

        // Step 5: Landlord signs (unilateral - this is sufficient for check-out)
        $checkOut = $this->signAction->execute(
            $checkOut,
            $landlordParticipant,
            'landlord-checkout-signature',
            '192.168.1.100',
            'Mozilla/5.0',
            'landlord-fp'
        );

        // Verify protocol transitioned to signed after initiator signs
        $checkOut->refresh();
        $this->assertEquals(ProtocolStatus::SIGNED, $checkOut->status);

        // Step 6: Issue the check-out act
        $checkOut = $this->issueAction->execute(
            $checkOut,
            72, // 72 hour objection window
            '192.168.1.100',
            'Mozilla/5.0'
        );

        // Step 7: Verify check-out state
        $checkOut->refresh();
        $this->assertEquals(ProtocolStatus::COMPLETED, $checkOut->status);
        $this->assertNotNull($checkOut->act_issued_at);
        $this->assertNotNull($checkOut->objection_window_ends_at);
        $this->assertTrue($checkOut->isObjectionWindowOpen());

        // Verify timeline events
        $events = InspectionEvent::where('protocol_id', $checkOut->id)
            ->pluck('event_type')
            ->filter()
            ->map(fn ($t) => $t->value)
            ->toArray();

        $this->assertContains('signature_added', $events);
        $this->assertContains('act_issued', $events);
        $this->assertContains('objection_window_opened', $events);

        // Verify only 1 acceptance (unilateral)
        $acceptanceCount = Acceptance::where('protocol_id', $checkOut->id)->count();
        $this->assertEquals(1, $acceptanceCount);
    }

    /**
     * §26.1: Check-out requires only initiator signature (asymmetric).
     */
    public function test_scenario_c_guard_1_checkout_requires_only_initiator_signature(): void
    {
        $checkOut = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Asymmetric test',
            'reference_mode' => ReferenceMode::SYSTEM_BASELINE,
            'linked_checkin_id' => $this->baselineCheckIn->id,
        ]);

        // Add landlord only
        $landlordParticipant = Participant::create([
            'protocol_id' => $checkOut->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
            'invited_at' => now(),
            'accepted_at' => now(),
            'signed_at' => now(),
        ]);

        // Add tenant but NOT signed (and that's fine for check-out)
        Participant::create([
            'protocol_id' => $checkOut->id,
            'user_id' => $this->tenant->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
            'invited_at' => now(),
            // Not accepted, not signed
        ]);

        $checkOut->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
        $checkOut->transitionTo(ProtocolStatus::PENDING_SIGNATURES);
        $checkOut->transitionTo(ProtocolStatus::SIGNED);

        // This should NOT throw - check-out only requires initiator
        $checkOut = $this->issueAction->execute($checkOut);

        $this->assertEquals(ProtocolStatus::COMPLETED, $checkOut->status);
        $this->assertNotNull($checkOut->act_issued_at);
    }

    /**
     * Test objection window calculation.
     */
    public function test_scenario_c_objection_window_72_hours(): void
    {
        $checkOut = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::SIGNED,
            'title' => 'Window test',
            'reference_mode' => ReferenceMode::SYSTEM_BASELINE,
            'linked_checkin_id' => $this->baselineCheckIn->id,
        ]);

        Participant::create([
            'protocol_id' => $checkOut->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
            'signed_at' => now(),
        ]);

        $checkOut = $this->issueAction->execute($checkOut, 72);

        $checkOut->refresh();

        // Window should end in ~72 hours
        $this->assertTrue($checkOut->isObjectionWindowOpen());
        $this->assertGreaterThanOrEqual(71, $checkOut->remainingObjectionHours());
        $this->assertLessThanOrEqual(72, $checkOut->remainingObjectionHours());
    }

    /**
     * Test objection window can be customized.
     */
    public function test_scenario_c_custom_objection_window(): void
    {
        $checkOut = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::SIGNED,
            'title' => 'Custom window test',
            'reference_mode' => ReferenceMode::SYSTEM_BASELINE,
            'linked_checkin_id' => $this->baselineCheckIn->id,
        ]);

        Participant::create([
            'protocol_id' => $checkOut->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
            'signed_at' => now(),
        ]);

        // 48 hour window
        $checkOut = $this->issueAction->execute($checkOut, 48);

        $checkOut->refresh();
        $this->assertGreaterThanOrEqual(47, $checkOut->remainingObjectionHours());
        $this->assertLessThanOrEqual(48, $checkOut->remainingObjectionHours());
    }

    /**
     * Test counterparty can raise objection within window.
     */
    public function test_scenario_c_counterparty_can_raise_objection(): void
    {
        $checkOut = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::COMPLETED,
            'title' => 'Objection test',
            'reference_mode' => ReferenceMode::SYSTEM_BASELINE,
            'linked_checkin_id' => $this->baselineCheckIn->id,
            'act_issued_at' => now(),
            'objection_window_ends_at' => now()->addHours(72),
        ]);

        $tenantParticipant = Participant::create([
            'protocol_id' => $checkOut->id,
            'user_id' => $this->tenant->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $raiseObjectionAction = app(RaiseObjectionAction::class);
        $objection = $raiseObjectionAction->execute(
            $checkOut,
            $tenantParticipant,
            'Sprzęt kuchenny był uszkodzony przed moim wprowadzeniem się.',
            null, // itemIds
            '192.168.1.200',
            'Tenant Browser'
        );

        $this->assertNotNull($objection);
        $this->assertEquals('Sprzęt kuchenny był uszkodzony przed moim wprowadzeniem się.', $objection->reason);
        $this->assertEquals($tenantParticipant->id, $objection->participant_id);

        // Verify objection event recorded
        $objectionEvent = InspectionEvent::where('protocol_id', $checkOut->id)
            ->where('event_type', InspectionEventType::OBJECTION_RAISED)
            ->first();

        $this->assertNotNull($objectionEvent);
    }

    /**
     * Test deposit amount tracking.
     */
    public function test_scenario_c_deposit_amount_tracked(): void
    {
        $checkOut = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Deposit test',
            'reference_mode' => ReferenceMode::SYSTEM_BASELINE,
            'linked_checkin_id' => $this->baselineCheckIn->id,
            'deposit_amount' => 5000.00,
        ]);

        $this->assertEquals(5000.00, $checkOut->deposit_amount);

        // Test amount_to_return when no damages
        $this->assertEquals(5000.00, $checkOut->amount_to_return);
    }

    /**
     * §26.7: Test all three reference modes work.
     */
    public function test_scenario_c_guard_7_system_baseline_reference_mode(): void
    {
        $checkOut = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Reference mode test',
            'reference_mode' => ReferenceMode::SYSTEM_BASELINE,
            'linked_checkin_id' => $this->baselineCheckIn->id,
        ]);

        $this->assertEquals(ReferenceMode::SYSTEM_BASELINE, $checkOut->reference_mode);
        $this->assertTrue($checkOut->reference_mode->requiresLinkedCheckin());
        $this->assertNotNull($checkOut->linked_checkin_id);
        $this->assertNotNull($checkOut->linkedCheckin);
    }

    /**
     * Test linked check-outs relationship from check-in.
     */
    public function test_scenario_c_checkin_has_linked_checkouts(): void
    {
        $checkOut = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Linked checkouts test',
            'reference_mode' => ReferenceMode::SYSTEM_BASELINE,
            'linked_checkin_id' => $this->baselineCheckIn->id,
        ]);

        $linkedCheckouts = $this->baselineCheckIn->linkedCheckouts;

        $this->assertEquals(1, $linkedCheckouts->count());
        $this->assertEquals($checkOut->id, $linkedCheckouts->first()->id);
    }
}
