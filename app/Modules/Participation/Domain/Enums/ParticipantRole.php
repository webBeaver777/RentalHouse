<?php

declare(strict_types=1);

namespace App\Modules\Participation\Domain\Enums;

enum ParticipantRole: string
{
    case LANDLORD = 'landlord';
    case TENANT = 'tenant';

    public function label(): string
    {
        return match ($this) {
            self::LANDLORD => 'Wynajmujący',
            self::TENANT => 'Najemca',
        };
    }

    /**
     * Get the opposite role.
     */
    public function opposite(): self
    {
        return match ($this) {
            self::LANDLORD => self::TENANT,
            self::TENANT => self::LANDLORD,
        };
    }
}
