<?php

namespace App\Modules\Protocol\Domain\Enums;

enum ProtocolStatus: string
{
    case DRAFT = 'draft';
    case PENDING_COUNTERPARTY = 'pending_counterparty';
    case PENDING_SIGNATURES = 'pending_signatures';
    case SIGNED = 'signed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Szkic',
            self::PENDING_COUNTERPARTY => 'Oczekuje na drugą stronę',
            self::PENDING_SIGNATURES => 'Oczekuje na podpisy',
            self::SIGNED => 'Podpisany',
            self::COMPLETED => 'Zakończony',
            self::CANCELLED => 'Anulowany',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING_COUNTERPARTY => 'warning',
            self::PENDING_SIGNATURES => 'info',
            self::SIGNED => 'success',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    /**
     * Check if protocol can be edited.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING_COUNTERPARTY]);
    }

    /**
     * Check if protocol is in final state.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED]);
    }

    /**
     * Get allowed transitions from current state.
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::PENDING_COUNTERPARTY, self::CANCELLED],
            self::PENDING_COUNTERPARTY => [self::DRAFT, self::PENDING_SIGNATURES, self::CANCELLED],
            self::PENDING_SIGNATURES => [self::SIGNED, self::CANCELLED],
            self::SIGNED => [self::COMPLETED],
            self::COMPLETED => [],
            self::CANCELLED => [],
        };
    }

    /**
     * Check if transition to given status is allowed.
     */
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions());
    }
}
