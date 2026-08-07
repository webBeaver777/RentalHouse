<?php

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M10.1, Scenario A slice: check-in protocol creation HTTP layer
 * (property -> draft check-in protocol -> draft show).
 *
 * Same convention as PropertyHttpTest: CSRF disabled here only,
 * auth/guest middleware and ownership checks stay exercised.
 */
class ProtocolHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
        ]);

        $this->property = Property::create([
            'user_id' => $this->user->id,
            'name' => 'Mieszkanie Testowe',
            'street' => 'ul. Testowa',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);
    }

    public function test_guest_is_redirected_from_protocol_create_to_login(): void
    {
        $response = $this->get(route('protocols.create', ['property_id' => $this->property->id]));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_create_page_for_own_property(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('protocols.create', ['property_id' => $this->property->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Protocol/Create')
            ->where('property.id', $this->property->id)
        );
    }

    public function test_user_cannot_view_create_page_for_someone_elses_property(): void
    {
        $otherUser = User::create([
            'name' => 'Anna Nowak',
            'email' => 'anna@example.com',
            'password' => 'password123',
        ]);

        $response = $this->actingAs($otherUser)
            ->get(route('protocols.create', ['property_id' => $this->property->id]));

        $response->assertNotFound();
    }

    public function test_authenticated_user_can_create_check_in_protocol(): void
    {
        $response = $this->actingAs($this->user)->post(route('protocols.store'), [
            'property_id' => $this->property->id,
        ]);

        $protocol = Protocol::where('property_id', $this->property->id)->firstOrFail();

        $response->assertRedirect(route('protocols.show', $protocol));

        $this->assertDatabaseHas('protocols', [
            'id' => $protocol->id,
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN->value,
            'status' => ProtocolStatus::DRAFT->value,
        ]);
    }

    public function test_creating_protocol_requires_property_id(): void
    {
        $response = $this->actingAs($this->user)->post(route('protocols.store'), []);

        $response->assertSessionHasErrors(['property_id']);
        $this->assertDatabaseCount('protocols', 0);
    }

    public function test_user_cannot_create_protocol_for_someone_elses_property(): void
    {
        $otherUser = User::create([
            'name' => 'Anna Nowak',
            'email' => 'anna@example.com',
            'password' => 'password123',
        ]);

        $response = $this->actingAs($otherUser)->post(route('protocols.store'), [
            'property_id' => $this->property->id,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('protocols', 0);
    }

    public function test_guest_cannot_create_protocol(): void
    {
        $response = $this->post(route('protocols.store'), [
            'property_id' => $this->property->id,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('protocols', 0);
    }

    public function test_authenticated_user_can_view_own_draft_protocol(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Protokół wjazdu — Mieszkanie Testowe',
        ]);

        $response = $this->actingAs($this->user)->get(route('protocols.show', $protocol));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Protocol/Show')
            ->where('protocol.id', $protocol->id)
            ->where('protocol.status', 'draft')
            ->where('protocol.type', 'check_in')
            ->where('protocol.initiator_name', 'Jan Kowalski')
        );
    }

    public function test_user_cannot_view_someone_elses_protocol(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Protokół wjazdu — Mieszkanie Testowe',
        ]);

        $otherUser = User::create([
            'name' => 'Anna Nowak',
            'email' => 'anna@example.com',
            'password' => 'password123',
        ]);

        $response = $this->actingAs($otherUser)->get(route('protocols.show', $protocol));

        $response->assertForbidden();
    }
}
