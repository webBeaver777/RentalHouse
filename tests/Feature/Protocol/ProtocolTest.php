<?php

namespace Tests\Feature\Protocol;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtocolTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->property = Property::create([
            'user_id' => $this->user->id,
            'name' => 'Test Property',
            'street' => 'Test Street',
            'building_number' => '1',
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);
    }

    /**
     * Protocol can be created with check-in type.
     */
    public function test_protocol_can_be_created_with_check_in_type(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Check-in Protocol',
        ]);

        $this->assertDatabaseHas('protocols', [
            'id' => $protocol->id,
            'type' => 'check_in',
            'status' => 'draft',
        ]);
    }

    /**
     * Protocol can be created with check-out type.
     */
    public function test_protocol_can_be_created_with_check_out_type(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_OUT,
            'title' => 'Check-out Protocol',
        ]);

        $this->assertEquals(ProtocolType::CHECK_OUT, $protocol->type);
    }

    /**
     * Protocol defaults to draft status.
     */
    public function test_protocol_defaults_to_draft_status(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Test Protocol',
        ]);

        $this->assertEquals(ProtocolStatus::DRAFT, $protocol->status);
    }

    /**
     * Protocol has UUID primary key.
     */
    public function test_protocol_has_uuid_primary_key(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Test Protocol',
        ]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $protocol->id
        );
    }

    /**
     * Protocol belongs to property.
     */
    public function test_protocol_belongs_to_property(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Test Protocol',
        ]);

        $this->assertEquals($this->property->id, $protocol->property->id);
    }

    /**
     * Protocol belongs to creator.
     */
    public function test_protocol_belongs_to_creator(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Test Protocol',
        ]);

        $this->assertEquals($this->user->id, $protocol->createdBy->id);
    }

    /**
     * Protocol can be soft deleted.
     */
    public function test_protocol_can_be_soft_deleted(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Test Protocol',
        ]);

        $protocolId = $protocol->id;
        $protocol->delete();

        $this->assertSoftDeleted('protocols', ['id' => $protocolId]);
    }

    /**
     * Protocol stores metadata as JSON.
     */
    public function test_protocol_stores_metadata_as_json(): void
    {
        $metadata = ['key' => 'value', 'nested' => ['data' => true]];

        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Test Protocol',
            'metadata' => $metadata,
        ]);

        $this->assertEquals($metadata, $protocol->fresh()->metadata);
    }
}
