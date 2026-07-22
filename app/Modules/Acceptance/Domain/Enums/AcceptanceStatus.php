<?php

declare(strict_types=1);

namespace App\Modules\Acceptance\Domain\Enums;

enum AcceptanceStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case DISPUTED = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Oczekuje',
            self::ACCEPTED => 'Zaakceptowano',
            self::DISPUTED => 'Kwestionowane',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::ACCEPTED => 'success',
            self::DISPUTED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::ACCEPTED => 'heroicon-o-check-circle',
            self::DISPUTED => 'heroicon-o-exclamation-triangle',
        };
    }
}
