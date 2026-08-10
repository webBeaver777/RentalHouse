<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Application\Actions;

use App\Modules\Billing\Application\Services\EntitlementService;
use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Exceptions\EntitlementRequiredException;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Domain\Enums\LegalMode;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Domain\Exceptions\ProtocolFinalizationException;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;

/**
 * Issue a check-out protocol (act).
 *
 * ASYMMETRIC RULE (§12.2, §26.1):
 * Check-out requires ONLY initiator acceptance to issue the act.
 * Counterparty gets an objection window (default 72h) but does NOT block issuance.
 *
 * D3 HARD-GATE: Requires consumed entitlement to issue act.
 *
 * This is fundamentally different from check-in:
 * - Check-in: 2 acceptances required for bilateral validity
 * - Check-out: 1 acceptance (initiator) + objection window opens
 *
 * This action:
 * 1. Validates protocol is check-out type
 * 2. Validates entitlement (D3 hard-gate)
 * 3. Checks that initiator has accepted
 * 4. Issues the act (act_issued_at)
 * 5. Opens objection window for counterparty
 * 6. Records events in timeline (D7)
 * 7. Does NOT wait for counterparty signature
 */
final class IssueCheckOutAction
{
    private const DEFAULT_OBJECTION_HOURS = 72;

    public function __construct(
        private readonly EntitlementService $entitlementService
    ) {}

    /**
     * Issue a check-out protocol.
     *
     * @throws ProtocolFinalizationException
     * @throws EntitlementRequiredException
     */
    public function execute(
        Protocol $protocol,
        ?int $objectionWindowHours = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): Protocol {
        $this->validate($protocol);

        // D3: HARD-GATE - require entitlement
        $initiator = $protocol->initiator();
        if ($initiator?->user) {
            $this->entitlementService->consumeEntitlement(
                $initiator->user,
                $protocol,
                AllowedAction::CREATE_CHECK_OUT,
                $ipAddress,
                $userAgent
            );
        }

        // legal_mode (M13 step 0, same pattern as FinalizeCheckInAction):
        // check-out is ALWAYS unilateral (Guard 1/11 — one acceptance issues
        // the act, the counterparty never blocks it), labelled by the
        // initiator's own role. Existing LegalMode cases only, no new rule.
        $protocol->legal_mode = $initiator?->role === ParticipantRole::LANDLORD
            ? LegalMode::UNILATERAL_LANDLORD
            : LegalMode::UNILATERAL_TENANT;

        $objectionHours = $objectionWindowHours ?? self::DEFAULT_OBJECTION_HOURS;

        $protocol->act_issued_at = now();
        $protocol->objection_window_ends_at = now()->addHours($objectionHours);
        $protocol->status = ProtocolStatus::COMPLETED;
        $protocol->completed_at = now();
        $protocol->save();

        // D7: Record timeline events
        InspectionEvent::record(
            $protocol,
            InspectionEventType::ACT_ISSUED,
            $initiator?->role?->value,
            $initiator?->user_id,
            null,
            $ipAddress,
            $userAgent
        );

        InspectionEvent::record(
            $protocol,
            InspectionEventType::OBJECTION_WINDOW_OPENED,
            'system',
            null,
            ['ends_at' => $protocol->objection_window_ends_at->toIso8601String()]
        );

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
