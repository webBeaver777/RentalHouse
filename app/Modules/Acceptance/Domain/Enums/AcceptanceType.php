<?php

declare(strict_types=1);

namespace App\Modules\Acceptance\Domain\Enums;

/**
 * G2: Types of acceptance actions.
 */
enum AcceptanceType: string
{
    case ACCEPTED = 'accepted';
    case ACCEPTED_WITH_COMMENTS = 'accepted_with_comments';
    case ACKNOWLEDGEMENT_ONLY = 'acknowledgement_only';

    public function label(): string
    {
        return match ($this) {
            self::ACCEPTED => 'Zaakceptowano',
            self::ACCEPTED_WITH_COMMENTS => 'Zaakceptowano z uwagami',
            self::ACKNOWLEDGEMENT_ONLY => 'Potwierdzenie odbioru',
        };
    }

    /**
     * Check if this represents full acceptance.
     */
    public function isFullAcceptance(): bool
    {
        return $this === self::ACCEPTED;
    }

    /**
     * Check if this includes comments/objections.
     */
    public function hasComments(): bool
    {
        return $this === self::ACCEPTED_WITH_COMMENTS;
    }
}
