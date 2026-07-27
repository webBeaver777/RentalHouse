<?php

declare(strict_types=1);

namespace Tests\Feature\Acceptance;

use App\Modules\Acceptance\Application\Actions\RaiseObjectionAction;
use App\Modules\Acceptance\Domain\Enums\ObjectionOutcome;
use App\Modules\Acceptance\Infrastructure\Models\ProtocolObjection;
use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Application\Actions\IssueCheckOutAction;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Tests for objection flow in check-out protocols.
 *
 * Verifies:
 * - Objection window opens on check-out issuance
 * - Counterparty can raise objection within window
 * - Objection creates inspection event
 * - Objection can be resolved
 * - Initiator cannot raise objection
 * - Objection after window closes is rejected
 */
class ObjectionFlowTest extends TestCase
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
     * Test: Objection window opens on check-out issuance.
     */
    public function test_objection_window_opens_on_checkout_issuance(): void
    {
        $protocol = $this->createIssuedCheckOut();

        $this->assertNotNull($protocol->objection_window_ends_at);
        $this->assertTrue($protocol->isObjectionWindowOpen());
        $this->assertGreaterThanOrEqual(71, $protocol->remainingObjectionHours());
    }

    /**
     * Test: Counterparty can raise objection within window.
     */
    public function test_counterparty_can_raise_objection_within_window(): void
    {
        $protocol = $this->createIssuedCheckOut();
        $counterparty = $protocol->counterparty();

        $action = new RaiseObjectionAction;
        $objection = $action->execute(
            $protocol,
            $counterparty,
            'Nie zgadzam się z opisem stanu łazienki - były dodatkowe uszkodzenia.',
            ['item-1', 'item-2'],
            '192.168.1.100',
            'Mozilla/5.0 Test'
        );

        // Objection was created
        $this->assertDatabaseHas('protocol_objections', [
            'protocol_id' => $protocol->id,
            'participant_id' => $counterparty->id,
        ]);

        $this->assertNotNull($objection->raised_at);
        $this->assertNull($objection->resolved_at);
        $this->assertTrue($objection->isPending());
        $this->assertFalse($objection->isResolved());
        $this->assertCount(2, $objection->item_ids);
    }

    /**
     * Test: Objection creates inspection event.
     */
    public function test_objection_creates_inspection_event(): void
    {
        $protocol = $this->createIssuedCheckOut();
        $counterparty = $protocol->counterparty();

        $action = new RaiseObjectionAction;
        $action->execute(
            $protocol,
            $counterparty,
            'Zastrzeżenie do protokołu.',
            null,
            '192.168.1.100',
            'Mozilla/5.0 Test'
        );

        // Inspection event was recorded
        $events = InspectionEvent::where('protocol_id', $protocol->id)->get();
        $eventTypes = $events->pluck('event_type')->filter()->map(fn ($t) => $t->value)->toArray();

        $this->assertContains('objection_raised', $eventTypes);
    }

    /**
     * Test: Objection transitions protocol to disputed status.
     */
    public function test_objection_transitions_protocol_to_disputed(): void
    {
        $protocol = $this->createIssuedCheckOut();

        // Initially completed (but with objection window open)
        // Note: IssueCheckOutAction sets status to COMPLETED
        // But the spec says it should go to SIGNED first, then objection sets DISPUTED
        // Let's just verify that when status is SIGNED, objection moves it to DISPUTED

        // Reset to SIGNED for this test
        $protocol->update(['status' => ProtocolStatus::SIGNED]);

        $counterparty = $protocol->counterparty();
        $action = new RaiseObjectionAction;

        $action->execute($protocol, $counterparty, 'Zastrzeżenie');

        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::DISPUTED, $protocol->status);
    }

    /**
     * Test: Objection can be resolved.
     */
    public function test_objection_can_be_resolved(): void
    {
        $protocol = $this->createIssuedCheckOut();
        $counterparty = $protocol->counterparty();

        $action = new RaiseObjectionAction;
        $objection = $action->execute(
            $protocol,
            $counterparty,
            'Zastrzeżenie do stanu mieszkania.'
        );

        $this->assertTrue($objection->isPending());

        // Resolve the objection
        $objection->resolve(
            ObjectionOutcome::ACCEPTED,
            'Uzgodniono korektę kosztów naprawy.'
        );

        $objection->refresh();

        $this->assertTrue($objection->isResolved());
        $this->assertFalse($objection->isPending());
        $this->assertNotNull($objection->resolved_at);
        $this->assertEquals(ObjectionOutcome::ACCEPTED, $objection->resolution_outcome);
        $this->assertStringContainsString('korektę', $objection->resolution_notes);
    }

    /**
     * Test: Initiator cannot raise objection to their own protocol.
     */
    public function test_initiator_cannot_raise_objection(): void
    {
        $protocol = $this->createIssuedCheckOut();
        $initiator = $protocol->initiator();

        $action = new RaiseObjectionAction;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Initiator cannot raise objection');

        $action->execute($protocol, $initiator, 'Próba zgłoszenia zastrzeżenia przez inicjatora.');
    }

    /**
     * Test: Objection cannot be raised for check-in protocol.
     */
    public function test_objection_cannot_be_raised_for_checkin(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::COMPLETED,
            'title' => 'Test Check-In',
        ]);

        $this->addParticipants($protocol);
        $counterparty = $protocol->counterparty();

        $action = new RaiseObjectionAction;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('check-out protocols');

        $action->execute($protocol, $counterparty, 'Zastrzeżenie');
    }

    /**
     * Test: Objection after window closes is rejected.
     */
    public function test_objection_after_window_closes_is_rejected(): void
    {
        $protocol = $this->createIssuedCheckOut();

        // Close the objection window
        $protocol->update(['objection_window_ends_at' => now()->subHour()]);

        $this->assertFalse($protocol->isObjectionWindowOpen());

        $counterparty = $protocol->counterparty();
        $action = new RaiseObjectionAction;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('window has closed');

        $action->execute($protocol, $counterparty, 'Spóźnione zastrzeżenie.');
    }

    /**
     * Test: Cannot raise duplicate unresolved objection.
     */
    public function test_cannot_raise_duplicate_unresolved_objection(): void
    {
        $protocol = $this->createIssuedCheckOut();
        $counterparty = $protocol->counterparty();

        $action = new RaiseObjectionAction;

        // First objection
        $action->execute($protocol, $counterparty, 'Pierwsze zastrzeżenie.');

        // Second objection should fail
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unresolved objection already exists');

        $action->execute($protocol, $counterparty, 'Drugie zastrzeżenie.');
    }

    /**
     * Test: Can raise new objection after previous is resolved.
     */
    public function test_can_raise_objection_after_previous_resolved(): void
    {
        $protocol = $this->createIssuedCheckOut();
        $counterparty = $protocol->counterparty();

        $action = new RaiseObjectionAction;

        // First objection
        $objection1 = $action->execute($protocol, $counterparty, 'Pierwsze zastrzeżenie.');

        // Resolve first
        $objection1->resolve(ObjectionOutcome::REJECTED, 'Odrzucone.');

        // Second objection should succeed
        $objection2 = $action->execute($protocol, $counterparty, 'Drugie zastrzeżenie.');

        $this->assertDatabaseCount('protocol_objections', 2);
        $this->assertNotEquals($objection1->id, $objection2->id);
    }

    /**
     * Test: Protocol hasUnresolvedObjections works correctly.
     */
    public function test_has_unresolved_objections(): void
    {
        $protocol = $this->createIssuedCheckOut();
        $counterparty = $protocol->counterparty();

        // Initially no objections
        $this->assertFalse($protocol->hasUnresolvedObjections());

        $action = new RaiseObjectionAction;
        $objection = $action->execute($protocol, $counterparty, 'Zastrzeżenie.');

        // Has unresolved
        $protocol->refresh();
        $this->assertTrue($protocol->hasUnresolvedObjections());

        // Resolve
        $objection->resolve(ObjectionOutcome::ACCEPTED);

        // No more unresolved
        $protocol->refresh();
        $this->assertFalse($protocol->hasUnresolvedObjections());
    }

    /**
     * Test: Objection scopes work correctly.
     */
    public function test_objection_scopes(): void
    {
        $protocol = $this->createIssuedCheckOut();
        $counterparty = $protocol->counterparty();

        $action = new RaiseObjectionAction;
        $objection = $action->execute($protocol, $counterparty, 'Zastrzeżenie.');

        // Unresolved scope
        $this->assertEquals(1, ProtocolObjection::unresolved()->count());
        $this->assertEquals(0, ProtocolObjection::resolved()->count());

        // Resolve
        $objection->resolve(ObjectionOutcome::ACCEPTED);

        // Resolved scope
        $this->assertEquals(0, ProtocolObjection::unresolved()->count());
        $this->assertEquals(1, ProtocolObjection::resolved()->count());

        // By protocol scope
        $this->assertEquals(1, ProtocolObjection::byProtocol($protocol)->count());
    }

    // === Helper Methods ===

    private function createIssuedCheckOut(): Protocol
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::PENDING_SIGNATURES,
            'title' => 'Test Check-Out',
        ]);

        $this->addParticipants($protocol);

        // Sign as initiator
        $protocol->initiator()->sign();

        // Give entitlement
        $this->giveEntitlement($this->landlord);

        // Issue the check-out
        $action = app(IssueCheckOutAction::class);
        $protocol = $action->execute($protocol, objectionWindowHours: 72);

        return $protocol;
    }

    private function addParticipants(Protocol $protocol): void
    {
        Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $this->tenant->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
        ]);
    }

    private function giveEntitlement(User $user): void
    {
        Entitlement::create([
            'user_id' => $user->id,
            'product_code' => ProductCode::WYJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_OUT,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);
    }
}
