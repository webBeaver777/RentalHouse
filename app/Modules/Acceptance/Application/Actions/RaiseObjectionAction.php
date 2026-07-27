<?php

declare(strict_types=1);

namespace App\Modules\Acceptance\Application\Actions;

use App\Modules\Acceptance\Infrastructure\Models\ProtocolObjection;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use InvalidArgumentException;

/**
 * Action for raising objections during the objection window.
 *
 * Only applicable to check-out protocols within the objection window.
 */
final class RaiseObjectionAction
{
    /**
     * Raise an objection to a check-out protocol.
     */
    public function execute(
        Protocol $protocol,
        Participant $participant,
        string $reason,
        ?array $itemIds = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ProtocolObjection {
        $this->validateCanObject($protocol, $participant);

        $objection = ProtocolObjection::create([
            'protocol_id' => $protocol->id,
            'participant_id' => $participant->id,
            'reason' => $reason,
            'item_ids' => $itemIds,
            'raised_at' => now(),
        ]);

        // D7: Record objection event
        InspectionEvent::record(
            $protocol,
            InspectionEventType::OBJECTION_RAISED,
            $participant->role->value,
            $participant->user_id,
            ['objection_id' => $objection->id, 'item_count' => count($itemIds ?? [])],
            $ipAddress,
            $userAgent
        );

        // Transition protocol to disputed status
        if ($protocol->status === ProtocolStatus::SIGNED) {
            $protocol->transitionTo(ProtocolStatus::DISPUTED);
        }

        return $objection;
    }

    /**
     * Validate if participant can raise objection.
     */
    private function validateCanObject(Protocol $protocol, Participant $participant): void
    {
        if ($protocol->type !== ProtocolType::CHECK_OUT) {
            throw new InvalidArgumentException(
                'Objections can only be raised for check-out protocols'
            );
        }

        if (! $protocol->isObjectionWindowOpen()) {
            throw new InvalidArgumentException(
                'Objection window has closed'
            );
        }

        if ($participant->isInitiator()) {
            throw new InvalidArgumentException(
                'Initiator cannot raise objection to their own protocol'
            );
        }

        if (! $protocol->hasParticipant($participant->user)) {
            throw new InvalidArgumentException(
                'User is not a participant in this protocol'
            );
        }

        // Check if already objected
        $existingObjection = ProtocolObjection::where('protocol_id', $protocol->id)
            ->where('participant_id', $participant->id)
            ->whereNull('resolved_at')
            ->exists();

        if ($existingObjection) {
            throw new InvalidArgumentException(
                'An unresolved objection already exists from this participant'
            );
        }
    }

    /**
     * Check if participant can raise objection.
     */
    public function canObject(Protocol $protocol, Participant $participant): bool
    {
        try {
            $this->validateCanObject($protocol, $participant);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
