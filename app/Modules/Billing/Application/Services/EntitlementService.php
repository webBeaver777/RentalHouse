<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Exceptions\EntitlementRequiredException;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use App\Modules\Billing\Infrastructure\Models\EntitlementUsage;
use App\Modules\Billing\Infrastructure\Models\Payment;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Protocol\Infrastructure\Models\Protocol;

/**
 * D2/D3: Entitlement service with HARD-GATING.
 *
 * Without consumed entitlement, these actions are BLOCKED:
 * - Sending magic-link to counterparty (check-in)
 * - Issuing act (check-out)
 * - Generating PDF
 *
 * NO FREE PLAN. Payment required.
 */
final class EntitlementService
{
    /**
     * Check if user has valid entitlement for an action.
     *
     * @throws EntitlementRequiredException
     */
    public function requireEntitlement(User $user, AllowedAction $action): Entitlement
    {
        $entitlement = $this->findValidEntitlement($user, $action);

        if ($entitlement === null) {
            throw new EntitlementRequiredException(
                "Brak ważnego uprawnienia do akcji: {$action->label()}. Wymagana jest opłata."
            );
        }

        return $entitlement;
    }

    /**
     * Check if user has valid entitlement (without throwing).
     */
    public function hasEntitlement(User $user, AllowedAction $action): bool
    {
        return $this->findValidEntitlement($user, $action) !== null;
    }

    /**
     * Find a valid entitlement for an action.
     */
    public function findValidEntitlement(User $user, AllowedAction $action): ?Entitlement
    {
        return Entitlement::where('user_id', $user->id)
            ->valid()
            ->forAction($action)
            ->orderBy('valid_until', 'asc') // Use expiring first
            ->first();
    }

    /**
     * Consume an entitlement for a protocol action.
     *
     * D3: This is the HARD GATE. Without this, actions are blocked.
     *
     * @throws EntitlementRequiredException
     */
    public function consumeEntitlement(
        User $user,
        Protocol $protocol,
        AllowedAction $action,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): EntitlementUsage {
        // Check if already consumed for this protocol+action
        $existingUsage = EntitlementUsage::where('protocol_id', $protocol->id)
            ->where('action', $action)
            ->first();

        if ($existingUsage !== null) {
            // Already consumed - allow the action
            return $existingUsage;
        }

        // Find and consume entitlement
        $entitlement = $this->requireEntitlement($user, $action);
        $entitlement->consume();

        // Record usage
        return EntitlementUsage::recordConsumption(
            $entitlement,
            $protocol,
            $action,
            $ipAddress,
            $userAgent
        );
    }

    /**
     * Check if protocol has consumed entitlement for an action.
     */
    public function hasConsumedEntitlement(Protocol $protocol, AllowedAction $action): bool
    {
        return EntitlementUsage::where('protocol_id', $protocol->id)
            ->where('action', $action)
            ->exists();
    }

    /**
     * Create entitlement from successful payment.
     */
    public function createEntitlementFromPayment(Payment $payment): Entitlement
    {
        $action = $payment->product_code->allowedAction();

        // Default validity: 12 months from payment
        $validUntil = $payment->paid_at?->addMonths(12) ?? now()->addMonths(12);

        return Entitlement::create([
            'user_id' => $payment->user_id,
            'product_code' => $payment->product_code,
            'allowed_action' => $action,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => $validUntil,
            'source_payment_id' => $payment->id,
            'activated_at' => now(),
        ]);
    }

    /**
     * Get user's entitlements summary.
     */
    public function getUserEntitlements(User $user): array
    {
        $entitlements = Entitlement::where('user_id', $user->id)
            ->valid()
            ->get();

        $summary = [];

        foreach (AllowedAction::mvpActions() as $action) {
            $forAction = $entitlements->filter(fn ($e) => $e->allowed_action === $action);

            $summary[$action->value] = [
                'action' => $action->value,
                'label' => $action->label(),
                'total_remaining' => $forAction->sum('remaining_quantity'),
                'entitlements' => $forAction->map(fn ($e) => [
                    'id' => $e->id,
                    'product_code' => $e->product_code->value,
                    'remaining' => $e->remaining_quantity,
                    'valid_until' => $e->valid_until?->toIso8601String(),
                ])->values()->toArray(),
            ];
        }

        return $summary;
    }

    /**
     * Get required action for protocol type.
     */
    public static function getRequiredAction(Protocol $protocol): AllowedAction
    {
        return $protocol->isCheckIn()
            ? AllowedAction::CREATE_CHECK_IN
            : AllowedAction::CREATE_CHECK_OUT;
    }
}
