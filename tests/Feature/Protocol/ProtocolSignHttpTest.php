<?php

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Modules\Acceptance\Infrastructure\Models\Acceptance;
use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Application\Services\InvitationService;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M10.5, Scenario A slice: initiator signs their own sent check-in
 * protocol — wires the same frozen SignProtocolAction the guest flow
 * uses (see ProtocolSignController / GuestInvitationHttpTest).
 */
class ProtocolSignHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

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

        $property = Property::create([
            'user_id' => $this->landlord->id,
            'name' => 'Mieszkanie Testowe',
            'street' => 'ul. Testowa',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->protocol = Protocol::create([
            'property_id' => $property->id,
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

        $this->invitationService->inviteByEmail($this->protocol, 'najemca@example.com', ParticipantRole::TENANT, false, 72);
        $this->protocol->submitToCounterparty();
    }

    private function otherUser(): User
    {
        return User::create([
            'name' => 'Anna Nowak',
            'email' => 'anna@example.com',
            'password' => 'password123',
        ]);
    }

    public function test_initiator_can_sign_and_acceptance_has_forensic_data(): void
    {
        $response = $this->actingAs($this->landlord)->post(route('protocols.sign', $this->protocol), [
            'device_fingerprint' => 'landlord-fingerprint',
        ]);

        $response->assertRedirect(route('protocols.show', $this->protocol));

        $acceptance = Acceptance::where('protocol_id', $this->protocol->id)
            ->where('accepted_by_role', ParticipantRole::LANDLORD->value)
            ->firstOrFail();

        $this->assertNotNull($acceptance->consent_text_snapshot);
        $this->assertEquals('landlord-fingerprint', $acceptance->device_fingerprint);
        $this->assertNotNull($acceptance->ip_address);

        $this->protocol->refresh();
        // Only initiator signed so far — still awaiting the guest.
        $this->assertEquals(ProtocolStatus::PENDING_SIGNATURES, $this->protocol->status);
    }

    public function test_signing_twice_is_rejected(): void
    {
        $this->actingAs($this->landlord)->post(route('protocols.sign', $this->protocol));

        $response = $this->actingAs($this->landlord)->post(route('protocols.sign', $this->protocol));

        $response->assertSessionHasErrors(['sign']);
        $this->assertEquals(1, Acceptance::where('protocol_id', $this->protocol->id)->count());
    }

    public function test_non_initiator_cannot_sign(): void
    {
        $response = $this->actingAs($this->otherUser())->post(route('protocols.sign', $this->protocol));

        $response->assertForbidden();
        $this->assertDatabaseCount('acceptances', 0);
    }

    public function test_guest_cannot_sign(): void
    {
        $response = $this->post(route('protocols.sign', $this->protocol));

        $response->assertRedirect(route('login'));
    }
}
