<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Application\Actions;

use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Domain\Exceptions\ProtocolFinalizationException;
use App\Modules\Protocol\Infrastructure\Models\Protocol;

/**
 * Finalize a check-in protocol.
 *
 * ASYMMETRIC RULE (§12.1, §26.1):
 * Check-in requires acceptance from BOTH parties to be considered bilateral/valid.
 * Without both signatures, it's documented as unilateral.
 *
 * This action:
 * 1. Validates protocol is check-in type
 * 2. Checks that initiator has accepted
 * 3. Checks that counterparty has accepted (for bilateral)
 * 4. Transitions to completed state
 */
final class FinalizeCheckInAction
{
    /**
     * Finalize a check-in protocol.
     *
     * @throws ProtocolFinalizationException
     */
    public function execute(Protocol $protocol, bool $allowUnilateral = false): Protocol
    {
        $this->validate($protocol, $allowUnilateral);

        $protocol->completed_at = now();
        $protocol->status = ProtocolStatus::COMPLETED;
        $protocol->save();

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
