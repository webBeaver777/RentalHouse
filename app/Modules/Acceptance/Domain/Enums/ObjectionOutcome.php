<?php

declare(strict_types=1);

namespace App\Modules\Acceptance\Domain\Enums;

enum ObjectionOutcome: string
{
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case PARTIALLY_ACCEPTED = 'partially_accepted';
    case WITHDRAWN = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::ACCEPTED => 'Zaakceptowano',
            self::REJECTED => 'Odrzucono',
            self::PARTIALLY_ACCEPTED => 'Częściowo zaakceptowano',
            self::WITHDRAWN => 'Wycofano',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACCEPTED => 'success',
            self::REJECTED => 'danger',
            self::PARTIALLY_ACCEPTED => 'warning',
            self::WITHDRAWN => 'gray',
        };
    }

    /**
     * Check if outcome favors the objector.
     */
    public function favorsObjector(): bool
    {
        return match ($this) {
            self::ACCEPTED, self::PARTIALLY_ACCEPTED => true,
            self::REJECTED, self::WITHDRAWN => false,
        };
    }
}
