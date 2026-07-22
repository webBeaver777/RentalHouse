<?php

namespace Tests\Feature\Protocol;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Domain\Services\ProtocolPermissions;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsymmetricStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;
    private User $tenant;
    private Protocol $protocol;
    private Participant $landlordParticipant;
    private Participant $tenantParticipant;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    // ============================================
    // DRAFT STATE - Initiator Permissions
    // ============================================

    /**
     * In draft state, initiator can edit.
     */
    public function test_in_draft_initiator_can_edit(): void
    {
        $permissions = ProtocolPermissions::for($this->protocol, $this->landlord);

        $this->assertTrue($permissions->canEdit());
    }

    /**
     * In draft state, initiator can submit to counterparty.
     */
    public function test_in_draft_initiator_can_submit_to_counterparty(): void
    {
        $permissions = ProtocolPermissions::for($this->protocol, $this->landlord);

        $this->assertTrue($permissions->canSubmitToCounterparty());
    }

    /**
     * In draft state, initiator can cancel.
     */
    public function test_in_draft_initiator_can_cancel(): void
    {
        $permissions = ProtocolPermissions::for($this->protocol, $this->landlord);

        $this->assertTrue($permissions->canCancel());
    }

    /**
     * In draft state, counterparty cannot edit.
     */
    public function test_in_draft_counterparty_cannot_edit(): void
    {
        $permissions = ProtocolPermissions::for($this->protocol, $this->tenant);

        $this->assertFalse($permissions->canEdit());
    }

    /**
     * In draft state, counterparty cannot submit.
     */
    public function test_in_draft_counterparty_cannot_submit(): void
    {
        $permissions = ProtocolPermissions::for($this->protocol, $this->tenant);

        $this->assertFalse($permissions->canSubmitToCounterparty());
    }

    // ============================================
    // PENDING_COUNTERPARTY STATE
    // ============================================

    /**
     * In pending_counterparty, counterparty can edit.
     */
    public function test_in_pending_counterparty_counterparty_can_edit(): void
    {
        $this->protocol->submitToCounterparty();
        $permissions = ProtocolPermissions::for($this->protocol, $this->tenant);

        $this->assertTrue($permissions->canEdit());
    }

    /**
     * In pending_counterparty, counterparty can request changes.
     */
    public function test_in_pending_counterparty_counterparty_can_request_changes(): void
    {
        $this->protocol->submitToCounterparty();
        $permissions = ProtocolPermissions::for($this->protocol, $this->tenant);

        $this->assertTrue($permissions->canRequestChanges());
    }

    /**
     * In pending_counterparty, counterparty can approve for signatures.
     */
    public function test_in_pending_counterparty_counterparty_can_approve(): void
    {
        $this->protocol->submitToCounterparty();
        $permissions = ProtocolPermissions::for($this->protocol, $this->tenant);

        $this->assertTrue($permissions->canApproveForSignatures());
    }

    /**
     * In pending_counterparty, initiator cannot edit.
     */
    public function test_in_pending_counterparty_initiator_cannot_edit(): void
    {
        $this->protocol->submitToCounterparty();
        $permissions = ProtocolPermissions::for($this->protocol, $this->landlord);

        $this->assertFalse($permissions->canEdit());
    }

    /**
     * In pending_counterparty, initiator cannot request changes.
     */
    public function test_in_pending_counterparty_initiator_cannot_request_changes(): void
    {
        $this->protocol->submitToCounterparty();
        $permissions = ProtocolPermissions::for($this->protocol, $this->landlord);

        $this->assertFalse($permissions->canRequestChanges());
    }

    // ============================================
    // PENDING_SIGNATURES STATE
    // ============================================

    /**
     * In pending_signatures, initiator can sign.
     */
    public function test_in_pending_signatures_initiator_can_sign(): void
    {
        $this->protocol->submitToCounterparty();
        $this->protocol->requestSignatures();
        $permissions = ProtocolPermissions::for($this->protocol, $this->landlord);

        $this->assertTrue($permissions->canSign());
    }

    /**
     * In pending_signatures, counterparty can sign.
     */
    public function test_in_pending_signatures_counterparty_can_sign(): void
    {
        $this->protocol->submitToCounterparty();
        $this->protocol->requestSignatures();
        $permissions = ProtocolPermissions::for($this->protocol, $this->tenant);

        $this->assertTrue($permissions->canSign());
    }

    /**
     * In pending_signatures, neither can edit.
     */
    public function test_in_pending_signatures_no_one_can_edit(): void
    {
        $this->protocol->submitToCounterparty();
        $this->protocol->requestSignatures();

        $landlordPerms = ProtocolPermissions::for($this->protocol, $this->landlord);
        $tenantPerms = ProtocolPermissions::for($this->protocol, $this->tenant);

        $this->assertFalse($landlordPerms->canEdit());
        $this->assertFalse($tenantPerms->canEdit());
    }

    /**
     * After signing, participant cannot sign again.
     */
    public function test_after_signing_cannot_sign_again(): void
    {
        $this->protocol->submitToCounterparty();
        $this->protocol->requestSignatures();

        $this->landlordParticipant->sign();

        $permissions = ProtocolPermissions::for($this->protocol->fresh(), $this->landlord);

        $this->assertFalse($permissions->canSign());
    }

    // ============================================
    // SIGNATURE TRACKING
    // ============================================

    /**
     * Participant can sign with signature data.
     */
    public function test_participant_can_sign_with_data(): void
    {
        $this->landlordParticipant->sign('base64-signature-data');

        $this->assertTrue($this->landlordParticipant->hasSigned());
        $this->assertEquals('base64-signature-data', $this->landlordParticipant->signature_data);
        $this->assertNotNull($this->landlordParticipant->signed_at);
    }

    /**
     * Protocol tracks all signed count.
     */
    public function test_protocol_tracks_signed_count(): void
    {
        $this->protocol->submitToCounterparty();
        $this->protocol->requestSignatures();

        $this->assertEquals(2, $this->protocol->unsignedParticipantsCount());
        $this->assertFalse($this->protocol->anyParticipantSigned());

        $this->landlordParticipant->sign();

        $this->assertEquals(1, $this->protocol->fresh()->unsignedParticipantsCount());
        $this->assertTrue($this->protocol->fresh()->anyParticipantSigned());
    }

    /**
     * Protocol auto-transitions to signed when all sign.
     */
    public function test_protocol_auto_transitions_when_all_sign(): void
    {
        $this->protocol->submitToCounterparty();
        $this->protocol->requestSignatures();

        $this->landlordParticipant->sign();
        $this->tenantParticipant->sign();

        $this->assertTrue($this->protocol->fresh()->allParticipantsSigned());
        $this->protocol->fresh()->checkAndTransitionToSigned();

        $this->assertEquals(ProtocolStatus::SIGNED, $this->protocol->fresh()->status);
    }

    // ============================================
    // AVAILABLE ACTIONS
    // ============================================

    /**
     * Get available actions for initiator in draft.
     */
    public function test_available_actions_for_initiator_in_draft(): void
    {
        $permissions = ProtocolPermissions::for($this->protocol, $this->landlord);
        $actions = $permissions->getAvailableActions();

        $this->assertContains('edit', $actions);
        $this->assertContains('submit_to_counterparty', $actions);
        $this->assertContains('cancel', $actions);
        $this->assertNotContains('sign', $actions);
    }

    /**
     * Get available actions for counterparty in pending_counterparty.
     */
    public function test_available_actions_for_counterparty_in_pending(): void
    {
        $this->protocol->submitToCounterparty();
        $permissions = ProtocolPermissions::for($this->protocol, $this->tenant);
        $actions = $permissions->getAvailableActions();

        $this->assertContains('edit', $actions);
        $this->assertContains('request_changes', $actions);
        $this->assertContains('approve_for_signatures', $actions);
        $this->assertContains('cancel', $actions);
    }

    // ============================================
    // NON-PARTICIPANT ACCESS
    // ============================================

    /**
     * Non-participant cannot view.
     */
    public function test_non_participant_cannot_view(): void
    {
        $outsider = User::create([
            'name' => 'Outsider',
            'email' => 'outsider@example.com',
            'password' => 'password123',
        ]);

        $permissions = ProtocolPermissions::for($this->protocol, $outsider);

        $this->assertFalse($permissions->canView());
        $this->assertFalse($permissions->canEdit());
        $this->assertFalse($permissions->canSign());
        $this->assertEmpty($permissions->getAvailableActions());
    }

    /**
     * Participant can view in any state.
     */
    public function test_participant_can_view_in_any_state(): void
    {
        $landlordPerms = ProtocolPermissions::for($this->protocol, $this->landlord);
        $tenantPerms = ProtocolPermissions::for($this->protocol, $this->tenant);

        $this->assertTrue($landlordPerms->canView());
        $this->assertTrue($tenantPerms->canView());

        $this->protocol->submitToCounterparty();

        $this->assertTrue($landlordPerms->canView());
        $this->assertTrue($tenantPerms->canView());
    }
}
