<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Domain\Contracts\PaymentGatewayInterface;
use App\Modules\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Payment;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Str;

/**
 * Fake payment gateway for testing.
 *
 * Used in test environment to simulate payments without hitting real APIs.
 * All transactions are automatically successful.
 */
final class FakePaymentGateway implements PaymentGatewayInterface
{
    /**
     * Auto-confirm payments immediately.
     */
    private bool $autoConfirm = false;

    /**
     * Simulate failure for next transaction.
     */
    private bool $shouldFail = false;

    /**
     * Last created payment for assertions.
     */
    private ?Payment $lastPayment = null;

    /**
     * Register a new transaction (immediate creation, no API call).
     */
    public function registerTransaction(
        User $user,
        ProductCode $product,
        int $amountInGrosze,
        string $description
    ): Payment {
        $sessionId = 'fake_'.Str::uuid()->toString();

        $this->lastPayment = Payment::create([
            'user_id' => $user->id,
            'p24_session_id' => $sessionId,
            'p24_order_id' => 'FAKE_'.strtoupper(Str::random(10)),
            'product_code' => $product,
            'amount' => $amountInGrosze,
            'currency' => 'PLN',
            'description' => $description,
            'status' => $this->shouldFail ? PaymentStatus::FAILED : PaymentStatus::PENDING,
            'buyer_email' => $user->email,
            'buyer_name' => $user->name,
            'is_sandbox' => true,
        ]);

        if ($this->shouldFail) {
            $this->lastPayment->update([
                'failure_reason' => 'Simulated failure for testing',
            ]);
            $this->shouldFail = false;
        } elseif ($this->autoConfirm) {
            $this->confirmPayment($this->lastPayment);
        }

        return $this->lastPayment;
    }

    /**
     * Get redirect URL (fake URL for testing).
     */
    public function getRedirectUrl(Payment $payment): string
    {
        return 'https://fake-gateway.test/pay/'.$payment->p24_session_id;
    }

    /**
     * Verify transaction (always true for fake gateway).
     */
    public function verifyTransaction(array $data): bool
    {
        $sessionId = $data['sessionId'] ?? '';
        $payment = Payment::bySessionId($sessionId)->first();

        if (! $payment) {
            return false;
        }

        return $this->confirmPayment($payment);
    }

    /**
     * Confirm payment as successful.
     */
    public function confirmPayment(Payment $payment): bool
    {
        if ($payment->status === PaymentStatus::PAID) {
            return true; // Already paid
        }

        $payment->update([
            'status' => PaymentStatus::PAID,
            'p24_transaction_id' => 'FAKE_TXN_'.strtoupper(Str::random(8)),
            'paid_at' => now(),
        ]);

        return true;
    }

    /**
     * Check if sandbox mode (always true for fake).
     */
    public function isSandbox(): bool
    {
        return true;
    }

    // === Test Helper Methods ===

    /**
     * Enable auto-confirm for all payments.
     */
    public function enableAutoConfirm(): self
    {
        $this->autoConfirm = true;

        return $this;
    }

    /**
     * Disable auto-confirm.
     */
    public function disableAutoConfirm(): self
    {
        $this->autoConfirm = false;

        return $this;
    }

    /**
     * Make next transaction fail.
     */
    public function failNextTransaction(): self
    {
        $this->shouldFail = true;

        return $this;
    }

    /**
     * Get last created payment.
     */
    public function getLastPayment(): ?Payment
    {
        return $this->lastPayment;
    }

    /**
     * Reset state for fresh test.
     */
    public function reset(): self
    {
        $this->autoConfirm = false;
        $this->shouldFail = false;
        $this->lastPayment = null;

        return $this;
    }
}
