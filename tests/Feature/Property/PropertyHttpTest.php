<?php

declare(strict_types=1);

namespace Tests\Feature\Property;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M10 GATE 2, Scenario A slice: public-facing property HTTP layer.
 *
 * Not to be confused with tests/Feature/Property/PropertyTest.php, which
 * covers the domain model and the Filament admin resource.
 *
 * CSRF (PreventRequestForgery) disabled here only, same convention as
 * PublicRegistrationTest — auth/guest middleware stays exercised.
 */
class PropertyHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
        ]);
    }

    public function test_guest_is_redirected_from_properties_index_to_login(): void
    {
        $response = $this->get(route('properties.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_properties_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('properties.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Properties/Index'));
    }

    public function test_properties_index_only_shows_own_properties(): void
    {
        $otherUser = User::create([
            'name' => 'Anna Nowak',
            'email' => 'anna@example.com',
            'password' => 'password123',
        ]);

        Property::create([
            'user_id' => $this->user->id,
            'name' => 'Moje mieszkanie',
            'street' => 'ul. Testowa',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        Property::create([
            'user_id' => $otherUser->id,
            'name' => 'Cudze mieszkanie',
            'street' => 'ul. Inna',
            'building_number' => '2',
            'city' => 'Kraków',
            'postal_code' => '30-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $response = $this->actingAs($this->user)->get(route('properties.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Properties/Index')
            ->has('properties', 1)
            ->where('properties.0.name', 'Moje mieszkanie')
        );
    }

    public function test_authenticated_user_can_view_create_property_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('properties.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Properties/Create'));
    }

    public function test_authenticated_user_can_create_property(): void
    {
        $response = $this->actingAs($this->user)->post(route('properties.store'), [
            'name' => 'Mieszkanie Kraków',
            'street' => 'ul. Floriańska',
            'building_number' => '10',
            'apartment_number' => '5',
            'city' => 'Kraków',
            'postal_code' => '31-021',
            'country' => 'PL',
            'declaration_type' => DeclarationType::OWNER->value,
            'description' => 'Przytulne mieszkanie w centrum.',
        ]);

        $response->assertRedirect(route('properties.index'));

        $this->assertDatabaseHas('properties', [
            'user_id' => $this->user->id,
            'name' => 'Mieszkanie Kraków',
            'declaration_type' => 'owner',
        ]);
    }

    public function test_creating_property_requires_required_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('properties.store'), []);

        $response->assertSessionHasErrors([
            'name', 'street', 'building_number', 'city', 'postal_code', 'declaration_type',
        ]);
        $this->assertDatabaseCount('properties', 0);
    }

    public function test_creating_property_defaults_country_to_pl_when_omitted(): void
    {
        $this->actingAs($this->user)->post(route('properties.store'), [
            'name' => 'Bez kraju',
            'street' => 'ul. Testowa',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::TENANT_DECLARED->value,
        ]);

        $this->assertDatabaseHas('properties', [
            'name' => 'Bez kraju',
            'country' => 'PL',
        ]);
    }

    public function test_guest_cannot_create_property(): void
    {
        $response = $this->post(route('properties.store'), [
            'name' => 'Nielegalny',
            'street' => 'ul. Testowa',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER->value,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('properties', 0);
    }
}
