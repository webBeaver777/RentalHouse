<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Billing\Application\Services\EntitlementService;
use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Domain\Exceptions\EntitlementRequiredException;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §26.12: Hard-gate test.
 *
 * Without consumed entitlement, these actions are BLOCKED:
 * 1. Sending magic-link to counterparty
 * 2. Issuing act (check-out)
 * 3. Generating PDF
 */
class HardGateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Property $property;

    private EntitlementService $entitlementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
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

        $this->entitlementService = new EntitlementService;
    }

    /**
     * §26.12: User without entitlement cannot perform check-in action.
     */
    public function test_guard_12_no_entitlement_blocks_check_in_action(): void
    {
        // User has NO entitlements

        $this->assertFalse(
            $this->entitlementService->hasEntitlement($this->user, AllowedAction::CREATE_CHECK_IN)
        );

        $this->expectException(EntitlementRequiredException::class);
        $this->entitlementService->requireEntitlement($this->user, AllowedAction::CREATE_CHECK_IN);
    }

    /**
     * §26.12: User without entitlement cannot perform check-out action.
     */
    public function test_guard_12_no_entitlement_blocks_check_out_action(): void
    {
        // User has NO entitlements

        $this->assertFalse(
            $this->entitlementService->hasEntitlement($this->user, AllowedAction::CREATE_CHECK_OUT)
        );

        $this->expectException(EntitlementRequiredException::class);
        $this->entitlementService->requireEntitlement($this->user, AllowedAction::CREATE_CHECK_OUT);
    }

    /**
     * §26.12: User WITH valid entitlement CAN perform action.
     */
    public function test_guard_12_with_entitlement_allows_action(): void
    {
        // Give user an entitlement
        Entitlement::create([
            'user_id' => $this->user->id,
            'product_code' => ProductCode::WJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_IN,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        $this->assertTrue(
            $this->entitlementService->hasEntitlement($this->user, AllowedAction::CREATE_CHECK_IN)
        );

        $entitlement = $this->entitlementService->requireEntitlement($this->user, AllowedAction::CREATE_CHECK_IN);
        $this->assertInstanceOf(Entitlement::class, $entitlement);
    }

    /**
     * §26.12: Entitlement consumption creates usage record.
     */
    public function test_guard_12_entitlement_consumption_creates_usage_record(): void
    {
        $entitlement = Entitlement::create([
            'user_id' => $this->user->id,
            'product_code' => ProductCode::WJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_IN,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'Test Protocol',
        ]);

        $usage = $this->entitlementService->consumeEntitlement(
            $this->user,
            $protocol,
            AllowedAction::CREATE_CHECK_IN
        );

        $this->assertNotNull($usage);
        $this->assertEquals($entitlement->id, $usage->entitlement_id);
        $this->assertEquals($protocol->id, $usage->protocol_id);

        // Entitlement quantity should be decremented
        $entitlement->refresh();
        $this->assertEquals(1, $entitlement->quantity_used);
    }

    /**
     * §26.12: Expired entitlement is not valid.
     */
    public function test_guard_12_expired_entitlement_is_not_valid(): void
    {
        Entitlement::create([
            'user_id' => $this->user->id,
            'product_code' => ProductCode::WJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_IN,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->subDay(), // Expired yesterday
            'activated_at' => now()->subMonth(),
        ]);

        $this->assertFalse(
            $this->entitlementService->hasEntitlement($this->user, AllowedAction::CREATE_CHECK_IN)
        );
    }

    /**
     * §26.12: Fully consumed entitlement is not valid.
     */
    public function test_guard_12_fully_consumed_entitlement_is_not_valid(): void
    {
        Entitlement::create([
            'user_id' => $this->user->id,
            'product_code' => ProductCode::WJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_IN,
            'quantity_total' => 1,
            'quantity_used' => 1, // Already used
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        $this->assertFalse(
            $this->entitlementService->hasEntitlement($this->user, AllowedAction::CREATE_CHECK_IN)
        );
    }

    /**
     * §26.6: Payment model supports multiple products (not hardcoded per protocol).
     */
    public function test_guard_6_entitlement_model_supports_multiple_products(): void
    {
        // WJAZD product
        $wjazd = Entitlement::create([
            'user_id' => $this->user->id,
            'product_code' => ProductCode::WJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_IN,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        // WYJAZD product
        $wyjazd = Entitlement::create([
            'user_id' => $this->user->id,
            'product_code' => ProductCode::WYJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_OUT,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        // Both should work independently
        $this->assertTrue($this->entitlementService->hasEntitlement($this->user, AllowedAction::CREATE_CHECK_IN));
        $this->assertTrue($this->entitlementService->hasEntitlement($this->user, AllowedAction::CREATE_CHECK_OUT));

        // Check forward-compatible products exist in enum
        $this->assertNotNull(ProductCode::FULL_CYCLE);
        $this->assertNotNull(ProductCode::LANDLORD_PRO);
        $this->assertNotNull(ProductCode::AGENCY);
    }
}
