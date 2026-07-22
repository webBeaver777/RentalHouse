<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Application\Actions;

use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Domain\Exceptions\ProtocolFinalizationException;
use App\Modules\Protocol\Infrastructure\Models\Protocol;

/**
 * Issue a check-out protocol (act).
 *
 * ASYMMETRIC RULE (§12.2, §26.1):
 * Check-out requires ONLY initiator acceptance to issue the act.
 * Counterparty gets an objection window (default 72h) but does NOT block issuance.
 *
 * This is fundamentally different from check-in:
 * - Check-in: 2 acceptances required for bilateral validity
 * - Check-out: 1 acceptance (initiator) + objection window opens
 *
 * This action:
 * 1. Validates protocol is check-out type
 * 2. Checks that initiator has accepted
 * 3. Issues the act (act_issued_at)
 * 4. Opens objection window for counterparty
 * 5. Does NOT wait for counterparty signature
 */
final class IssueCheckOutAction
{
    private const DEFAULT_OBJECTION_HOURS = 72;

    /**
     * Issue a check-out protocol.
     *
     * @throws ProtocolFinalizationException
     */
    public function execute(Protocol $protocol, ?int $objectionWindowHours = null): Protocol
    {
        $this->validate($protocol);

        $objectionHours = $objectionWindowHours ?? self::DEFAULT_OBJECTION_HOURS;

        $protocol->act_issued_at = now();
        $protocol->objection_window_ends_at = now()->addHours($objectionHours);
        $protocol->status = ProtocolStatus::COMPLETED;
        $protocol->completed_at = now();
        $protocol->save();

        return $protocol;
    }

    /**
     * Validate that protocol can be issued.
     *
     * @throws ProtocolFinalizationException
     */
    private function validate(Protocol $protocol): void
    {
        // Must be check-out
        if ($protocol->type !== ProtocolType::CHECK_OUT) {
            throw ProtocolFinalizationException::wrongType(
                'IssueCheckOutAction can only be used for check-out protocols'
            );
        }

        // Must be in correct status
        if (! in_array($protocol->status, [ProtocolStatus::PENDING_SIGNATURES, ProtocolStatus::SIGNED, ProtocolStatus::PENDING_COUNTERPARTY])) {
            throw ProtocolFinalizationException::invalidStatus(
                "Cannot issue check-out in status: {$protocol->status->value}"
            );
        }

        // Check initiator acceptance ONLY
        $initiator = $protocol->initiator();
        if (! $initiator || ! $initiator->hasSigned()) {
            throw ProtocolFinalizationException::missingAcceptance(
                'Initiator must sign to issue the check-out act'
            );
        }

        // NOTE: We explicitly DO NOT check counterparty signature here
        // This is the key asymmetry - check-out does not require counterparty signature
    }

    /**
     * Check if protocol can be issued.
     */
    public function canIssue(Protocol $protocol): bool
    {
        try {
            $this->validate($protocol);

            return true;
        } catch (ProtocolFinalizationException) {
            return false;
        }
    }

    /**
     * Get required acceptances count for check-out issuance.
     */
    public function requiredAcceptancesCount(): int
    {
        return 1; // Only initiator needs to accept
    }

    /**
     * Get default objection window hours.
     */
    public function defaultObjectionWindowHours(): int
    {
        return self::DEFAULT_OBJECTION_HOURS;
    }
}
