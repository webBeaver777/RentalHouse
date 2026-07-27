<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Application\Actions;

use App\Modules\Billing\Application\Services\EntitlementService;
use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Exceptions\EntitlementRequiredException;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Domain\Exceptions\ProtocolFinalizationException;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;

/**
 * Finalize a check-in protocol.
 *
 * ASYMMETRIC RULE (§12.1, §26.1):
 * Check-in requires acceptance from BOTH parties to be considered bilateral/valid.
 * Without both signatures, it's documented as unilateral.
 *
 * D3 HARD-GATE: Requires consumed entitlement to finalize.
 *
 * This action:
 * 1. Validates protocol is check-in type
 * 2. Validates entitlement (D3 hard-gate)
 * 3. Checks that initiator has accepted
 * 4. Checks that counterparty has accepted (for bilateral)
 * 5. Transitions to completed state
 * 6. Records event in timeline (D7)
 */
final class FinalizeCheckInAction
{
    public function __construct(
        private readonly EntitlementService $entitlementService
    ) {}

    /**
     * Finalize a check-in protocol.
     *
     * @throws ProtocolFinalizationException
     * @throws EntitlementRequiredException
     */
    public function execute(
        Protocol $protocol,
        bool $allowUnilateral = false,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): Protocol {
        $this->validate($protocol, $allowUnilateral);

        // D3: HARD-GATE - require entitlement
        $initiator = $protocol->initiator();
        if ($initiator?->user) {
            $this->entitlementService->consumeEntitlement(
                $initiator->user,
                $protocol,
                AllowedAction::CREATE_CHECK_IN,
                $ipAddress,
                $userAgent
            );
        }

        $protocol->completed_at = now();
        $protocol->status = ProtocolStatus::COMPLETED;
        $protocol->save();

        // D7: Record timeline event
        InspectionEvent::record(
            $protocol,
            InspectionEventType::PROTOCOL_FINALIZED,
            $initiator?->role?->value,
            $initiator?->user_id,
            ['unilateral' => $allowUnilateral],
            $ipAddress,
            $userAgent
        );

        return $protocol;
    }

    /**
     * Validate that protocol can be finalized.
     *
     * @throws ProtocolFinalizationException
     */
    private function validate(Protocol $protocol, bool $allowUnilateral): void
    {
        // Must be check-in
        if ($protocol->type !== ProtocolType::CHECK_IN) {
            throw ProtocolFinalizationException::wrongType(
                'FinalizeCheckInAction can only be used for check-in protocols'
            );
        }

        // Must be in correct status
        if (! in_array($protocol->status, [ProtocolStatus::PENDING_SIGNATURES, ProtocolStatus::SIGNED])) {
            throw ProtocolFinalizationException::invalidStatus(
                "Cannot finalize check-in in status: {$protocol->status->value}"
            );
        }

        // Check initiator acceptance
        $initiator = $protocol->initiator();
        if (! $initiator || ! $initiator->hasSigned()) {
            throw ProtocolFinalizationException::missingAcceptance(
                'Initiator must sign the check-in protocol'
            );
        }

        // Check counterparty acceptance (required for bilateral)
        $counterparty = $protocol->counterparty();
        if ($counterparty && ! $counterparty->hasSigned()) {
            if (! $allowUnilateral) {
                throw ProtocolFinalizationException::missingAcceptance(
                    'Counterparty must sign for bilateral check-in. Use allowUnilateral=true for unilateral.'
                );
            }
        }
    }

    /**
     * Check if protocol can be finalized.
     */
    public function canFinalize(Protocol $protocol, bool $allowUnilateral = false): bool
    {
        try {
            $this->validate($protocol, $allowUnilateral);

            return true;
        } catch (ProtocolFinalizationException) {
            return false;
        }
    }

    /**
     * Get required acceptances count for bilateral check-in.
     */
    public function requiredAcceptancesCount(): int
    {
        return 2; // Both parties must accept
    }
}
