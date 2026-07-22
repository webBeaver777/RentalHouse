<?php

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Application\Actions\FinalizeCheckInAction;
use App\Modules\Protocol\Application\Actions\IssueCheckOutAction;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Domain\Exceptions\ProtocolFinalizationException;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $action = new IssueCheckOutAction;
        $this->assertTrue($action->canIssue($protocol));

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
        $action = new FinalizeCheckInAction;
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
        $checkInAction = new FinalizeCheckInAction;
        $checkOutAction = new IssueCheckOutAction;

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
     * Test: No shared finalize() method exists.
     *
     * The protocol should NOT have a generic finalize() that works for both types.
     */
    public function test_no_shared_finalize_method(): void
    {
        $protocol = new Protocol;

        // There should be no generic finalize() method
        $this->assertFalse(
            method_exists($protocol, 'finalize'),
            'Protocol should NOT have a generic finalize() method. Use FinalizeCheckInAction or IssueCheckOutAction instead.'
        );
    }

    /**
     * Test: Check-in finalization requires both signatures.
     */
    public function test_checkin_requires_both_signatures_for_bilateral(): void
    {
        $protocol = $this->createCheckInProtocol();
        $this->addParticipants($protocol);

        $action = new FinalizeCheckInAction;

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

        $action = new IssueCheckOutAction;
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

        $action = new FinalizeCheckInAction;

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

        $action = new IssueCheckOutAction;

        $this->expectException(ProtocolFinalizationException::class);
        $action->execute($protocol);
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
}
