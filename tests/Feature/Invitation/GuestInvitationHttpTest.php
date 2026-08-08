<?php

declare(strict_types=1);

namespace Tests\Feature\Invitation;

use App\Modules\Acceptance\Infrastructure\Models\Acceptance;
use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Application\Services\InvitationService;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\InvitationToken;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M10.5, Scenario A slice: guest (no account) accepts + signs via
 * magic-link. Access control is the existing InvitationToken hash
 * comparison (G3) — these tests prove that guard as much as the happy
 * path, plus the dual-sign auto-transition to signed.
 */
class GuestInvitationHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Property $property;

    private Protocol $protocol;

    private InvitationService $invitationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->landlord = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
        ]);

        $this->property = Property::create([
            'user_id' => $this->landlord->id,
            'name' => 'Mieszkanie Testowe',
            'street' => 'ul. Testowa',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Protokół wjazdu',
        ]);

        $this->invitationService = app(InvitationService::class);
        $this->invitationService->addInitiator($this->protocol, $this->landlord, ParticipantRole::LANDLORD);

        // Matches the real M10.4 order: landlord pays before inviting —
        // inviteByEmail() hard-gates on this (see InvitationService).
        Entitlement::create([
            'user_id' => $this->landlord->id,
            'product_code' => ProductCode::WJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_IN,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);
    }

    /**
     * Invites the guest tenant and moves the protocol to pending_counterparty,
     * matching the real M10.4 flow order. Returns the raw token.
     */
    private function inviteGuest(): string
    {
        $token = $this->invitationService->inviteByEmail(
            $this->protocol,
            'najemca@example.com',
            ParticipantRole::TENANT,
            false,
            72,
        );

        $this->protocol->submitToCounterparty();

        return $token->raw_token;
    }

    /* --- access control --- */

    public function test_valid_token_grants_read_only_access(): void
    {
        $token = $this->inviteGuest();

        $response = $this->get(route('invitation.accept', $token));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invitation/Accept')
            ->where('protocol.id', $this->protocol->id)
            ->where('participant.role_label', 'Najemca')
            ->where('already_signed', false)
        );
    }

    public function test_nonexistent_token_is_rejected_gracefully(): void
    {
        $response = $this->get(route('invitation.accept', 'this-token-does-not-exist'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invitation/Invalid')
            ->where('reason', 'not_found')
        );
    }

    public function test_expired_token_is_rejected_gracefully(): void
    {
        $token = $this->inviteGuest();

        InvitationToken::where('token_hash', hash('sha256', $token))
            ->update(['expires_at' => now()->subHour()]);

        $response = $this->get(route('invitation.accept', $token));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invitation/Invalid')
            ->where('reason', 'expired')
        );
    }

    public function test_revoked_token_is_rejected_gracefully(): void
    {
        $token = $this->inviteGuest();

        InvitationToken::where('token_hash', hash('sha256', $token))
            ->update(['revoked_at' => now()]);

        $response = $this->get(route('invitation.accept', $token));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invitation/Invalid')
            ->where('reason', 'revoked')
        );
    }

    /* --- sign: forensic data + acceptance --- */

    public function test_guest_can_sign_and_acceptance_has_forensic_data(): void
    {
        $token = $this->inviteGuest();

        $response = $this->post(route('invitation.sign', $token), [
            'consent' => true,
            'device_fingerprint' => 'test-browser-fingerprint',
        ]);

        $response->assertRedirect(route('invitation.accept', $token));

        $acceptance = Acceptance::where('protocol_id', $this->protocol->id)
            ->where('accepted_by_role', ParticipantRole::TENANT->value)
            ->firstOrFail();

        $this->assertNotNull($acceptance->consent_text_snapshot);
        $this->assertEquals('test-browser-fingerprint', $acceptance->device_fingerprint);
        $this->assertNotNull($acceptance->ip_address);
        $this->assertNotNull($acceptance->user_agent);
    }

    public function test_sign_without_consent_is_rejected(): void
    {
        $token = $this->inviteGuest();

        $response = $this->post(route('invitation.sign', $token), [
            'device_fingerprint' => 'test-fp',
        ]);

        $response->assertSessionHasErrors(['consent']);
        $this->assertDatabaseCount('acceptances', 0);
    }

    /* --- dual sign -> auto signed --- */

    public function test_two_signatures_auto_transition_protocol_to_signed(): void
    {
        $token = $this->inviteGuest();

        // Guest signs first.
        $this->post(route('invitation.sign', $token), ['consent' => true]);

        $this->protocol->refresh();
        $this->assertEquals(ProtocolStatus::PENDING_SIGNATURES, $this->protocol->status);

        // Initiator signs via the authenticated route.
        $this->actingAs($this->landlord)->post(route('protocols.sign', $this->protocol));

        $this->protocol->refresh();
        $this->assertEquals(ProtocolStatus::SIGNED, $this->protocol->status);
    }

    /* --- no double sign / reused token --- */

    public function test_reusing_token_after_signing_is_rejected(): void
    {
        $token = $this->inviteGuest();

        $this->post(route('invitation.sign', $token), ['consent' => true]);

        // Token is now used — a second sign attempt with the same link must fail.
        $response = $this->post(route('invitation.sign', $token), ['consent' => true]);

        $response->assertNotFound();
        $this->assertDatabaseCount('acceptances', 1);
    }

    public function test_viewing_link_after_signing_shows_already_signed(): void
    {
        $token = $this->inviteGuest();
        $this->post(route('invitation.sign', $token), ['consent' => true]);

        $response = $this->get(route('invitation.accept', $token));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invitation/Accept')
            ->where('already_signed', true)
        );
    }
}
