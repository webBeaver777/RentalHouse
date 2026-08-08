<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Billing\Application\Services\EntitlementService;
use App\Modules\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Payment;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M10.4 tracking-tail: dev-grant must go through the SAME emitter method as
 * a real Payment-driven entitlement — not an inline duplicate. This proves
 * the two paths produce entitlements of the same shape (they funnel through
 * EntitlementService::emitEntitlement()), differing only in
 * source_payment_id (null for the dev stand-in).
 */
class EntitlementEmitterUnificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_grant_and_payment_path_produce_same_entitlement_shape(): void
    {
        $devUser = User::create([
            'name' => 'Dev User',
            'email' => 'dev@example.com',
            'password' => 'password123',
        ]);

        $payingUser = User::create([
            'name' => 'Paying User',
            'email' => 'paying@example.com',
            'password' => 'password123',
        ]);

        $service = app(EntitlementService::class);

        $devEntitlement = $service->createDevEntitlement($devUser, ProductCode::WJAZD);

        $payment = Payment::create([
            'user_id' => $payingUser->id,
            'p24_session_id' => 'test-session-'.uniqid(),
            'product_code' => ProductCode::WJAZD,
            'amount' => 4900,
            'currency' => 'PLN',
            'description' => 'Test payment for emitter unification',
            'status' => PaymentStatus::PAID,
            'paid_at' => now(),
            'is_verified' => true,
            'buyer_email' => $payingUser->email,
            'is_sandbox' => true,
        ]);
        $paymentEntitlement = $service->createEntitlementFromPayment($payment);

        // Same shape from the same emitter.
        $this->assertEquals($devEntitlement->allowed_action, $paymentEntitlement->allowed_action);
        $this->assertEquals($devEntitlement->product_code, $paymentEntitlement->product_code);
        $this->assertEquals($devEntitlement->quantity_total, $paymentEntitlement->quantity_total);
        $this->assertEquals($devEntitlement->quantity_used, $paymentEntitlement->quantity_used);
        $this->assertEqualsWithDelta(
            $devEntitlement->valid_until->timestamp,
            $paymentEntitlement->valid_until->timestamp,
            5
        );

        // Only real distinguishing field: where it came from.
        $this->assertNull($devEntitlement->source_payment_id);
        $this->assertEquals($payment->id, $paymentEntitlement->source_payment_id);
    }
}
