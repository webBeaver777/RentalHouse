<?php

namespace Tests\Feature\Protocol;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProtocolStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private Protocol $protocol;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $property = Property::create([
            'user_id' => $user->id,
            'name' => 'Test Property',
            'street' => 'Test Street',
            'building_number' => '1',
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->protocol = Protocol::create([
            'property_id' => $property->id,
            'created_by_user_id' => $user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Test Protocol',
        ]);
    }

    /**
     * Draft protocol can transition to pending_counterparty.
     */
    public function test_draft_can_transition_to_pending_counterparty(): void
    {
        $this->protocol->submitToCounterparty();

        $this->assertEquals(ProtocolStatus::PENDING_COUNTERPARTY, $this->protocol->status);
    }

    /**
     * Draft protocol can be cancelled.
     */
    public function test_draft_can_be_cancelled(): void
    {
        $this->protocol->cancel();

        $this->assertEquals(ProtocolStatus::CANCELLED, $this->protocol->status);
    }

    /**
     * Draft protocol is editable.
     */
    public function test_draft_is_editable(): void
    {
        $this->assertTrue($this->protocol->isEditable());
    }

    /**
     * Pending counterparty can transition to pending signatures.
     */
    public function test_pending_counterparty_can_transition_to_pending_signatures(): void
    {
        $this->protocol->submitToCounterparty();
        $this->protocol->requestSignatures();

        $this->assertEquals(ProtocolStatus::PENDING_SIGNATURES, $this->protocol->status);
    }

    /**
     * Pending counterparty can revert to draft.
     */
    public function test_pending_counterparty_can_revert_to_draft(): void
    {
        $this->protocol->submitToCounterparty();
        $this->protocol->revertToDraft();

        $this->assertEquals(ProtocolStatus::DRAFT, $this->protocol->status);
    }

    /**
     * Pending counterparty is still editable.
     */
    public function test_pending_counterparty_is_editable(): void
    {
        $this->protocol->submitToCounterparty();

        $this->assertTrue($this->protocol->isEditable());
    }

    /**
     * Pending signatures can transition to signed.
     */
    public function test_pending_signatures_can_transition_to_signed(): void
    {
        $this->protocol->submitToCounterparty();
        $this->protocol->requestSignatures();
        $this->protocol->markAsSigned();

        $this->assertEquals(ProtocolStatus::SIGNED, $this->protocol->status);
    }

    /**
     * Pending signatures is not editable.
     */
    public function test_pending_signatures_is_not_editable(): void
    {
        $this->protocol->submitToCounterparty();
        $this->protocol->requestSignatures();

        $this->assertFalse($this->protocol->isEditable());
    }

    /**
     * Signed can transition to completed.
     */
    public function test_signed_can_transition_to_completed(): void
    {
        $this->protocol->submitToCounterparty();
        $this->protocol->requestSignatures();
        $this->protocol->markAsSigned();
        $this->protocol->complete();

        $this->assertEquals(ProtocolStatus::COMPLETED, $this->protocol->status);
    }

    /**
     * Completed protocol sets completed_at timestamp.
     */
    public function test_completed_sets_completed_at_timestamp(): void
    {
        $this->protocol->submitToCounterparty();
        $this->protocol->requestSignatures();
        $this->protocol->markAsSigned();
        $this->protocol->complete();

        $this->assertNotNull($this->protocol->completed_at);
    }

    /**
     * Completed protocol is final.
     */
    public function test_completed_is_final(): void
    {
        $this->protocol->submitToCounterparty();
        $this->protocol->requestSignatures();
        $this->protocol->markAsSigned();
        $this->protocol->complete();

        $this->assertTrue($this->protocol->isFinal());
    }

    /**
     * Cancelled protocol is final.
     */
    public function test_cancelled_is_final(): void
    {
        $this->protocol->cancel();

        $this->assertTrue($this->protocol->isFinal());
    }

    /**
     * Invalid transition throws exception.
     */
    public function test_invalid_transition_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Cannot go directly from draft to signed
        $this->protocol->transitionTo(ProtocolStatus::SIGNED);
    }

    /**
     * Completed protocol cannot transition.
     */
    public function test_completed_cannot_transition(): void
    {
        $this->protocol->submitToCounterparty();
        $this->protocol->requestSignatures();
        $this->protocol->markAsSigned();
        $this->protocol->complete();

        $this->assertEmpty($this->protocol->allowedTransitions());
    }

    /**
     * Can check allowed transitions.
     */
    public function test_can_check_allowed_transitions(): void
    {
        $allowed = $this->protocol->allowedTransitions();

        $this->assertContains(ProtocolStatus::PENDING_COUNTERPARTY, $allowed);
        $this->assertContains(ProtocolStatus::CANCELLED, $allowed);
        $this->assertNotContains(ProtocolStatus::SIGNED, $allowed);
    }

    /**
     * Can check if transition is allowed.
     */
    public function test_can_check_if_transition_is_allowed(): void
    {
        $this->assertTrue($this->protocol->canTransitionTo(ProtocolStatus::PENDING_COUNTERPARTY));
        $this->assertFalse($this->protocol->canTransitionTo(ProtocolStatus::COMPLETED));
    }
}
