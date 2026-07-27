<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Contracts;

use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Payment;
use App\Modules\Identity\Infrastructure\Models\User;

/**
 * PaymentGateway interface for payment provider abstraction.
 *
 * Allows swapping between Przelewy24 (production) and FakeGateway (testing).
 */
interface PaymentGatewayInterface
{
    /**
     * Register a new transaction.
     */
    public function registerTransaction(
        User $user,
        ProductCode $product,
        int $amountInGrosze,
        string $description
    ): Payment;

    /**
     * Get redirect URL for payment.
     */
    public function getRedirectUrl(Payment $payment): string;

    /**
     * Verify transaction from webhook/callback data.
     */
    public function verifyTransaction(array $data): bool;

    /**
     * Confirm transaction as paid (for sandbox/testing).
     */
    public function confirmPayment(Payment $payment): bool;

    /**
     * Check if gateway is in sandbox mode.
     */
    public function isSandbox(): bool;
}
