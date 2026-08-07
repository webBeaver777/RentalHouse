<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M10 GATE 2: entitlements/access HTTP layer.
 *
 * devGrant() is a temporary P24 stand-in — see BillingController docblock.
 * These tests exist to prove the guardrail (404 outside dev/local/testing)
 * as much as the happy path, so the stub can't leak into production.
 */
class BillingHttpTest extends TestCase
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

    public function test_guest_is_redirected_from_billing_index_to_login(): void
    {
        $this->get(route('billing.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_billing_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('billing.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Billing/Entitlements')
            ->where('devModeAvailable', true)
            ->has('products', 2)
        );
    }

    public function test_dev_grant_creates_usable_entitlement(): void
    {
        $response = $this->actingAs($this->user)->post(route('billing.dev-grant'), [
            'product_code' => 'WJAZD',
        ]);

        $response->assertRedirect(route('billing.index'));

        $this->assertDatabaseHas('entitlements', [
            'user_id' => $this->user->id,
            'product_code' => 'WJAZD',
            'allowed_action' => AllowedAction::CREATE_CHECK_IN->value,
        ]);
    }

    public function test_dev_grant_rejects_invalid_product_code(): void
    {
        $response = $this->actingAs($this->user)->post(route('billing.dev-grant'), [
            'product_code' => 'FULL_CYCLE',
        ]);

        $response->assertSessionHasErrors('product_code');
        $this->assertDatabaseCount('entitlements', 0);
    }

    public function test_dev_grant_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $response = $this->actingAs($this->user)->post(route('billing.dev-grant'), [
            'product_code' => 'WJAZD',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('entitlements', 0);
    }

    public function test_guest_cannot_dev_grant(): void
    {
        $response = $this->post(route('billing.dev-grant'), [
            'product_code' => 'WJAZD',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('entitlements', 0);
    }
}
