<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Domain\Services;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Infrastructure\Models\Protocol;

class ProtocolPermissions
{
    public function __construct(
        private Protocol $protocol,
        private User $user,
    ) {}

    /**
     * Get the participant record for this user.
     */
    public function getParticipant(): ?Participant
    {
        return $this->protocol->participants()
            ->where('user_id', $this->user->id)
            ->first();
    }

    /**
     * Check if user is a participant.
     */
    public function isParticipant(): bool
    {
        return $this->getParticipant() !== null;
    }

    /**
     * Check if user is the initiator.
     */
    public function isInitiator(): bool
    {
        return $this->getParticipant()?->isInitiator() ?? false;
    }

    /**
     * Check if user is the counterparty.
     */
    public function isCounterparty(): bool
    {
        return $this->getParticipant()?->isCounterparty() ?? false;
    }

    /**
     * Check if user can view the protocol.
     */
    public function canView(): bool
    {
        return $this->isParticipant();
    }

    /**
     * Check if user can edit the protocol content.
     */
    public function canEdit(): bool
    {
        if (! $this->isParticipant()) {
            return false;
        }

        $status = $this->protocol->status;

        // In draft, only initiator can edit
        if ($status === ProtocolStatus::DRAFT) {
            return $this->isInitiator();
        }

        // In pending_counterparty, counterparty can suggest changes
        if ($status === ProtocolStatus::PENDING_COUNTERPARTY) {
            return $this->isCounterparty();
        }

        // No editing in other states
        return false;
    }

    /**
     * Check if user can submit protocol to counterparty.
     */
    public function canSubmitToCounterparty(): bool
    {
        return $this->isInitiator()
            && $this->protocol->status === ProtocolStatus::DRAFT;
    }

    /**
     * Check if user can request changes (send back to draft).
     */
    public function canRequestChanges(): bool
    {
        return $this->isCounterparty()
            && $this->protocol->status === ProtocolStatus::PENDING_COUNTERPARTY;
    }

    /**
     * Check if user can approve and proceed to signatures.
     */
    public function canApproveForSignatures(): bool
    {
        return $this->isCounterparty()
            && $this->protocol->status === ProtocolStatus::PENDING_COUNTERPARTY;
    }

    /**
     * Check if user can sign the protocol.
     */
    public function canSign(): bool
    {
        if (! $this->isParticipant()) {
            return false;
        }

        if ($this->protocol->status !== ProtocolStatus::PENDING_SIGNATURES) {
            return false;
        }

        $participant = $this->getParticipant();

        // Can sign if not already signed
        return $participant->signed_at === null;
    }

    /**
     * Check if user can cancel the protocol.
     */
    public function canCancel(): bool
    {
        if (! $this->isParticipant()) {
            return false;
        }

        $status = $this->protocol->status;

        // Can cancel in draft or pending states
        return in_array($status, [
            ProtocolStatus::DRAFT,
            ProtocolStatus::PENDING_COUNTERPARTY,
            ProtocolStatus::PENDING_SIGNATURES,
        ]);
    }

    /**
     * Get all available actions for this user.
     */
    public function getAvailableActions(): array
    {
        $actions = [];

        if ($this->canEdit()) {
            $actions[] = 'edit';
        }

        if ($this->canSubmitToCounterparty()) {
            $actions[] = 'submit_to_counterparty';
        }

        if ($this->canRequestChanges()) {
            $actions[] = 'request_changes';
        }

        if ($this->canApproveForSignatures()) {
            $actions[] = 'approve_for_signatures';
        }

        if ($this->canSign()) {
            $actions[] = 'sign';
        }

        if ($this->canCancel()) {
            $actions[] = 'cancel';
        }

        return $actions;
    }

    /**
     * Create instance for protocol and user.
     */
    public static function for(Protocol $protocol, User $user): self
    {
        return new self($protocol, $user);
    }
}
