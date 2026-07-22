<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Domain\Enums;

/**
 * Severity levels for defects/damage.
 */
enum DefectSeverity: string
{
    case MINOR = 'minor';           // Drobna usterka
    case MODERATE = 'moderate';     // Średnia usterka
    case MAJOR = 'major';           // Poważna usterka
    case CRITICAL = 'critical';     // Krytyczna usterka

    public function label(): string
    {
        return match ($this) {
            self::MINOR => 'Drobna',
            self::MODERATE => 'Średnia',
            self::MAJOR => 'Poważna',
            self::CRITICAL => 'Krytyczna',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MINOR => 'info',
            self::MODERATE => 'warning',
            self::MAJOR => 'danger',
            self::CRITICAL => 'danger',
        };
    }

    /**
     * Get suggested cost multiplier for deposit calculation.
     */
    public function costMultiplier(): float
    {
        return match ($this) {
            self::MINOR => 0.1,
            self::MODERATE => 0.3,
            self::MAJOR => 0.7,
            self::CRITICAL => 1.0,
        };
    }
}
