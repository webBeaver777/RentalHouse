<?php

declare(strict_types=1);

namespace Tests\Feature\Participation;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantTest extends TestCase
{
    use RefreshDatabase;

    private User $landlordUser;

    private User $tenantUser;

    private Protocol $protocol;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlordUser = User::create([
            'name' => 'Landlord User',
            'email' => 'landlord@example.com',
            'password' => 'password123',
        ]);

        $this->tenantUser = User::create([
            'name' => 'Tenant User',
            'email' => 'tenant@example.com',
            'password' => 'password123',
        ]);

        $property = Property::create([
            'user_id' => $this->landlordUser->id,
            'name' => 'Test Property',
            'street' => 'Test Street',
            'building_number' => '1',
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->protocol = Protocol::create([
            'property_id' => $property->id,
            'created_by_user_id' => $this->landlordUser->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Test Protocol',
        ]);
    }

    /**
     * Participant can be created as landlord.
     */
    public function test_participant_can_be_created_as_landlord(): void
    {
        $participant = Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlordUser->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        $this->assertDatabaseHas('participants', [
            'id' => $participant->id,
            'role' => 'landlord',
            'is_initiator' => true,
        ]);
    }

    /**
     * Participant can be created as tenant.
     */
    public function test_participant_can_be_created_as_tenant(): void
    {
        $participant = Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->tenantUser->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
        ]);

        $this->assertEquals(ParticipantRole::TENANT, $participant->role);
    }

    /**
     * Participant belongs to protocol.
     */
    public function test_participant_belongs_to_protocol(): void
    {
        $participant = Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlordUser->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        $this->assertEquals($this->protocol->id, $participant->protocol->id);
    }

    /**
     * Participant belongs to user.
     */
    public function test_participant_belongs_to_user(): void
    {
        $participant = Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlordUser->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        $this->assertEquals($this->landlordUser->id, $participant->user->id);
    }

    /**
     * Participant can be invited by email.
     */
    public function test_participant_can_be_invited_by_email(): void
    {
        $participant = Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => null,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
            'invited_email' => 'newtenant@example.com',
            'invited_at' => now(),
        ]);

        $this->assertNull($participant->user);
        $this->assertEquals('newtenant@example.com', $participant->invited_email);
        $this->assertTrue($participant->isPending());
    }

    /**
     * Participant can accept invitation.
     */
    public function test_participant_can_accept_invitation(): void
    {
        $participant = Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->tenantUser->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
            'invited_at' => now(),
        ]);

        $participant->accept();

        $this->assertTrue($participant->isAccepted());
        $this->assertFalse($participant->isPending());
        $this->assertNotNull($participant->accepted_at);
    }

    /**
     * Participant can decline invitation.
     */
    public function test_participant_can_decline_invitation(): void
    {
        $participant = Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->tenantUser->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
            'invited_at' => now(),
        ]);

        $participant->decline();

        $this->assertTrue($participant->isDeclined());
        $this->assertFalse($participant->isPending());
        $this->assertNotNull($participant->declined_at);
    }

    /**
     * Initiator check works.
     */
    public function test_initiator_check_works(): void
    {
        $initiator = Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlordUser->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        $counterparty = Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->tenantUser->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
        ]);

        $this->assertTrue($initiator->isInitiator());
        $this->assertFalse($initiator->isCounterparty());

        $this->assertFalse($counterparty->isInitiator());
        $this->assertTrue($counterparty->isCounterparty());
    }

    /**
     * Display name shows user name.
     */
    public function test_display_name_shows_user_name(): void
    {
        $participant = Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlordUser->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        $this->assertEquals('Landlord User', $participant->display_name);
    }

    /**
     * Display name shows invited email when no user.
     */
    public function test_display_name_shows_invited_email(): void
    {
        $participant = Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => null,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
            'invited_email' => 'invited@example.com',
        ]);

        $this->assertEquals('invited@example.com', $participant->display_name);
    }

    /**
     * Role opposite works correctly.
     */
    public function test_role_opposite_works(): void
    {
        $this->assertEquals(ParticipantRole::TENANT, ParticipantRole::LANDLORD->opposite());
        $this->assertEquals(ParticipantRole::LANDLORD, ParticipantRole::TENANT->opposite());
    }

    /**
     * Protocol has participants relationship.
     */
    public function test_protocol_has_participants(): void
    {
        Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlordUser->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->tenantUser->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
        ]);

        $this->assertEquals(2, $this->protocol->participants()->count());
    }

    /**
     * Protocol can get initiator.
     */
    public function test_protocol_can_get_initiator(): void
    {
        Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlordUser->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        $initiator = $this->protocol->initiator();

        $this->assertNotNull($initiator);
        $this->assertEquals($this->landlordUser->id, $initiator->user_id);
    }

    /**
     * Protocol can get counterparty.
     */
    public function test_protocol_can_get_counterparty(): void
    {
        Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlordUser->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->tenantUser->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
        ]);

        $counterparty = $this->protocol->counterparty();

        $this->assertNotNull($counterparty);
        $this->assertEquals($this->tenantUser->id, $counterparty->user_id);
    }

    /**
     * Protocol can get landlord.
     */
    public function test_protocol_can_get_landlord(): void
    {
        Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlordUser->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        $landlord = $this->protocol->landlord();

        $this->assertNotNull($landlord);
        $this->assertEquals(ParticipantRole::LANDLORD, $landlord->role);
    }

    /**
     * Protocol can get tenant.
     */
    public function test_protocol_can_get_tenant(): void
    {
        Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->tenantUser->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
        ]);

        $tenant = $this->protocol->tenant();

        $this->assertNotNull($tenant);
        $this->assertEquals(ParticipantRole::TENANT, $tenant->role);
    }

    /**
     * Protocol can check if user is participant.
     */
    public function test_protocol_can_check_participant(): void
    {
        Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlordUser->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        $this->assertTrue($this->protocol->hasParticipant($this->landlordUser));
        $this->assertFalse($this->protocol->hasParticipant($this->tenantUser));
    }

    /**
     * Protocol can get participant role.
     */
    public function test_protocol_can_get_participant_role(): void
    {
        Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlordUser->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        $role = $this->protocol->getParticipantRole($this->landlordUser);

        $this->assertEquals(ParticipantRole::LANDLORD, $role);
        $this->assertNull($this->protocol->getParticipantRole($this->tenantUser));
    }

    /**
     * Participant can be soft deleted.
     */
    public function test_participant_can_be_soft_deleted(): void
    {
        $participant = Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlordUser->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        $participantId = $participant->id;
        $participant->delete();

        $this->assertSoftDeleted('participants', ['id' => $participantId]);
    }

    /**
     * Scopes work correctly.
     */
    public function test_scopes_work_correctly(): void
    {
        Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlordUser->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->tenantUser->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
            'invited_at' => now(),
        ]);

        $this->assertEquals(1, Participant::initiators()->count());
        $this->assertEquals(1, Participant::counterparties()->count());
        $this->assertEquals(1, Participant::withRole(ParticipantRole::LANDLORD)->count());
        $this->assertEquals(1, Participant::pending()->count());
    }
}
