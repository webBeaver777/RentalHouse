<?php

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\InvitationToken;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M10.4, Scenario A slice: submitting a draft check-in protocol to the
 * counterparty — hard-gated on a real, consumed entitlement via
 * InvitationService::inviteByEmail() (no new gate logic in
 * ProtocolSubmitController, see its docblock).
 *
 * Same convention as ProtocolRoomItemHttpTest: CSRF disabled here only.
 */
class ProtocolSubmitHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Protocol $draftProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
        ]);

        $property = Property::create([
            'user_id' => $this->user->id,
            'name' => 'Mieszkanie Testowe',
            'street' => 'ul. Testowa',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->draftProtocol = Protocol::create([
            'property_id' => $property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Protokół wjazdu',
        ]);
    }

    private function otherUser(): User
    {
        return User::create([
            'name' => 'Anna Nowak',
            'email' => 'anna@example.com',
            'password' => 'password123',
        ]);
    }

    private function grantEntitlement(User $user): void
    {
        Entitlement::create([
            'user_id' => $user->id,
            'product_code' => ProductCode::WJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_IN,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);
    }

    /* --- hard-gate: the key test --- */

    public function test_submit_without_entitlement_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->post(
            route('protocols.submit', $this->draftProtocol),
            ['counterparty_email' => 'najemca@example.com']
        );

        $response->assertSessionHasErrors(['counterparty_email']);

        $this->draftProtocol->refresh();
        $this->assertEquals(ProtocolStatus::DRAFT, $this->draftProtocol->status);
        $this->assertDatabaseCount('invitation_tokens', 0);
        // No lingering counterparty participant either — the whole attempt
        // rolls back inside the DB transaction when the gate rejects.
        $this->assertDatabaseCount('participants', 0);
    }

    /* --- success path --- */

    public function test_submit_with_entitlement_transitions_and_creates_invitation(): void
    {
        $this->grantEntitlement($this->user);

        $response = $this->actingAs($this->user)->post(
            route('protocols.submit', $this->draftProtocol),
            ['counterparty_email' => 'najemca@example.com']
        );

        $response->assertRedirect(route('protocols.show', $this->draftProtocol));

        $this->draftProtocol->refresh();
        $this->assertEquals(ProtocolStatus::PENDING_COUNTERPARTY, $this->draftProtocol->status);
        $this->assertEquals(ParticipantRole::LANDLORD, $this->draftProtocol->initiator_role);
        $this->assertEquals(ParticipantRole::TENANT, $this->draftProtocol->counterparty_role);

        $this->assertDatabaseHas('participants', [
            'protocol_id' => $this->draftProtocol->id,
            'user_id' => $this->user->id,
            'role' => ParticipantRole::LANDLORD->value,
            'is_initiator' => true,
        ]);

        $counterparty = Participant::where('protocol_id', $this->draftProtocol->id)
            ->where('is_initiator', false)
            ->firstOrFail();

        $this->assertEquals('najemca@example.com', $counterparty->invited_email);
        $this->assertEquals(ParticipantRole::TENANT, $counterparty->role);

        $this->assertDatabaseHas('invitation_tokens', [
            'participant_id' => $counterparty->id,
        ]);
        // G3 invariant untouched: only the hash is stored (sha256 hex), never the raw token.
        $token = InvitationToken::where('participant_id', $counterparty->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token->token_hash);

        $this->assertNotNull($this->draftProtocol->fresh()->metadata['dev_magic_link'] ?? null);
    }

    public function test_entitlement_is_consumed_on_successful_submit(): void
    {
        $this->grantEntitlement($this->user);

        $this->actingAs($this->user)->post(
            route('protocols.submit', $this->draftProtocol),
            ['counterparty_email' => 'najemca@example.com']
        );

        $entitlement = Entitlement::where('user_id', $this->user->id)->firstOrFail();
        $this->assertEquals(1, $entitlement->quantity_used);
    }

    /* --- guard: draft-only + initiator --- */

    public function test_submit_is_rejected_on_non_draft_protocol(): void
    {
        $this->grantEntitlement($this->user);

        $protocol = Protocol::create([
            'property_id' => $this->draftProtocol->property_id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Protokół w toku',
            'status' => ProtocolStatus::PENDING_COUNTERPARTY,
        ]);

        $response = $this->actingAs($this->user)->post(
            route('protocols.submit', $protocol),
            ['counterparty_email' => 'najemca@example.com']
        );

        $response->assertStatus(422);
    }

    public function test_non_initiator_cannot_submit(): void
    {
        $otherUser = $this->otherUser();
        $this->grantEntitlement($otherUser);

        $response = $this->actingAs($otherUser)->post(
            route('protocols.submit', $this->draftProtocol),
            ['counterparty_email' => 'najemca@example.com']
        );

        $response->assertForbidden();

        $this->draftProtocol->refresh();
        $this->assertEquals(ProtocolStatus::DRAFT, $this->draftProtocol->status);
    }

    public function test_guest_cannot_submit(): void
    {
        $response = $this->post(
            route('protocols.submit', $this->draftProtocol),
            ['counterparty_email' => 'najemca@example.com']
        );

        $response->assertRedirect(route('login'));
    }

    public function test_submit_requires_valid_email(): void
    {
        $this->grantEntitlement($this->user);

        $response = $this->actingAs($this->user)->post(
            route('protocols.submit', $this->draftProtocol),
            ['counterparty_email' => 'not-an-email']
        );

        $response->assertSessionHasErrors(['counterparty_email']);
        $this->draftProtocol->refresh();
        $this->assertEquals(ProtocolStatus::DRAFT, $this->draftProtocol->status);
    }
}
