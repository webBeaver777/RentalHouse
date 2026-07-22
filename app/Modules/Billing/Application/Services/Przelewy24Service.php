<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Payment;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * D1: Przelewy24 payment integration service.
 *
 * Replaces StripeWebhookService.
 * Implements: transaction registration, redirect, status verification, webhook, sandbox mode.
 */
final class Przelewy24Service
{
    private string $merchantId;

    private string $posId;

    private string $crc;

    private string $apiKey;

    private bool $sandbox;

    private string $baseUrl;

    public function __construct()
    {
        $this->sandbox = config('billing.przelewy24.sandbox', true);
        $this->merchantId = config('billing.przelewy24.merchant_id', '');
        $this->posId = config('billing.przelewy24.pos_id', '');
        $this->crc = config('billing.przelewy24.crc', '');
        $this->apiKey = config('billing.przelewy24.api_key', '');

        $this->baseUrl = $this->sandbox
            ? 'https://sandbox.przelewy24.pl'
            : 'https://secure.przelewy24.pl';
    }

    /**
     * Register a new transaction with P24.
     */
    public function registerTransaction(
        User $user,
        ProductCode $product,
        int $amountInGrosze,
        string $description
    ): Payment {
        // Create local payment record
        $sessionId = $this->generateSessionId();

        $payment = Payment::create([
            'user_id' => $user->id,
            'p24_session_id' => $sessionId,
            'product_code' => $product,
            'amount' => $amountInGrosze,
            'currency' => 'PLN',
            'description' => $description,
            'status' => PaymentStatus::PENDING,
            'buyer_email' => $user->email,
            'buyer_name' => $user->name,
            'is_sandbox' => $this->sandbox,
        ]);

        // In sandbox/test mode, we might skip actual API call
        if ($this->sandbox && empty($this->merchantId)) {
            Log::info("P24 Sandbox: Skipping API registration for session {$sessionId}");

            return $payment;
        }

        // Register transaction with P24 API
        $response = $this->callApi('/api/v1/transaction/register', [
            'merchantId' => (int) $this->merchantId,
            'posId' => (int) $this->posId,
            'sessionId' => $sessionId,
            'amount' => $amountInGrosze,
            'currency' => 'PLN',
            'description' => $description,
            'email' => $user->email,
            'country' => 'PL',
            'language' => 'pl',
            'urlReturn' => route('payment.return', ['session' => $sessionId]),
            'urlStatus' => route('payment.webhook'),
            'sign' => $this->generateSign([
                'sessionId' => $sessionId,
                'merchantId' => $this->merchantId,
                'amount' => $amountInGrosze,
                'currency' => 'PLN',
            ]),
        ]);

        if (isset($response['data']['token'])) {
            $payment->update([
                'p24_order_id' => $response['data']['token'],
            ]);
        }

        return $payment;
    }

    /**
     * Get redirect URL for payment.
     */
    public function getRedirectUrl(Payment $payment): string
    {
        if ($payment->p24_order_id) {
            return "{$this->baseUrl}/trnRequest/{$payment->p24_order_id}";
        }

        // Fallback for sandbox without real API
        return route('payment.sandbox', ['session' => $payment->p24_session_id]);
    }

    /**
     * Verify transaction status via webhook or callback.
     */
    public function verifyTransaction(array $data): bool
    {
        $sessionId = $data['sessionId'] ?? '';
        $orderId = $data['orderId'] ?? '';
        $amount = $data['amount'] ?? 0;
        $currency = $data['currency'] ?? 'PLN';
        $receivedSign = $data['sign'] ?? '';

        $payment = Payment::bySessionId($sessionId)->first();

        if (! $payment) {
            Log::warning("P24 Verify: Payment not found for session {$sessionId}");

            return false;
        }

        // Verify signature
        $expectedSign = $this->generateSign([
            'sessionId' => $sessionId,
            'orderId' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        if (! hash_equals($expectedSign, $receivedSign)) {
            Log::warning("P24 Verify: Invalid signature for session {$sessionId}");
            $payment->markAsFailed('Invalid signature');

            return false;
        }

        // Verify amount matches
        if ((int) $amount !== $payment->amount) {
            Log::warning("P24 Verify: Amount mismatch for session {$sessionId}");
            $payment->markAsFailed('Amount mismatch');

            return false;
        }

        // Call P24 transaction/verify endpoint
        if (! $this->sandbox || ! empty($this->merchantId)) {
            $verifyResponse = $this->callApi('/api/v1/transaction/verify', [
                'merchantId' => (int) $this->merchantId,
                'posId' => (int) $this->posId,
                'sessionId' => $sessionId,
                'amount' => $amount,
                'currency' => $currency,
                'orderId' => $orderId,
                'sign' => $expectedSign,
            ]);

            if (! isset($verifyResponse['data']['status']) || $verifyResponse['data']['status'] !== 'success') {
                Log::warning("P24 Verify: Verification failed for session {$sessionId}");
                $payment->markAsFailed('Verification failed');

                return false;
            }
        }

        // Mark payment as successful
        $payment->markAsPaid((string) $orderId);
        $payment->markAsVerified($receivedSign);

        Log::info("P24: Payment verified successfully for session {$sessionId}");

        return true;
    }

    /**
     * Handle webhook from P24.
     */
    public function handleWebhook(array $data): void
    {
        $sessionId = $data['sessionId'] ?? '';

        $payment = Payment::bySessionId($sessionId)->first();

        if (! $payment) {
            Log::warning("P24 Webhook: Payment not found for session {$sessionId}");

            return;
        }

        // Update payment status based on webhook data
        if (isset($data['statement']) && $data['statement'] === 'OK') {
            $this->verifyTransaction($data);
        } else {
            $payment->markAsFailed($data['errorMessage'] ?? 'Unknown error');
        }
    }

    /**
     * Generate session ID for new transaction.
     */
    private function generateSessionId(): string
    {
        return 'R2P_'.now()->format('Ymd_His').'_'.Str::random(8);
    }

    /**
     * Generate sign for P24 API.
     */
    private function generateSign(array $params): string
    {
        $json = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha384', $json.$this->crc);
    }

    /**
     * Call P24 API.
     */
    private function callApi(string $endpoint, array $data): array
    {
        $auth = base64_encode("{$this->posId}:{$this->apiKey}");

        try {
            $response = Http::withHeaders([
                'Authorization' => "Basic {$auth}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}{$endpoint}", $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("P24 API Error: {$response->status()}", [
                'endpoint' => $endpoint,
                'response' => $response->json(),
            ]);

            return ['error' => $response->json()];
        } catch (\Exception $e) {
            Log::error("P24 API Exception: {$e->getMessage()}", [
                'endpoint' => $endpoint,
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Simulate successful payment in sandbox mode.
     */
    public function simulateSandboxPayment(Payment $payment): bool
    {
        if (! $this->sandbox) {
            return false;
        }

        $payment->markAsPaid('SANDBOX_'.Str::random(10));
        $payment->markAsVerified('sandbox_verification');

        Log::info("P24 Sandbox: Simulated successful payment for session {$payment->p24_session_id}");

        return true;
    }
}
