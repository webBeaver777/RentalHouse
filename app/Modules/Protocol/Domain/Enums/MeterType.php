<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Domain\Enums;

/**
 * Types of utility meters tracked in protocols.
 */
enum MeterType: string
{
    case ELECTRICITY = 'electricity';       // prąd
    case COLD_WATER = 'cold_water';         // woda zimna
    case HOT_WATER = 'hot_water';           // woda ciepła
    case GAS = 'gas';                       // gaz
    case HEATING = 'heating';               // ogrzewanie

    public function label(): string
    {
        return match ($this) {
            self::ELECTRICITY => 'Prąd',
            self::COLD_WATER => 'Woda zimna',
            self::HOT_WATER => 'Woda ciepła',
            self::GAS => 'Gaz',
            self::HEATING => 'Ogrzewanie',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::ELECTRICITY => 'kWh',
            self::COLD_WATER, self::HOT_WATER => 'm³',
            self::GAS => 'm³',
            self::HEATING => 'GJ',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ELECTRICITY => 'bolt',
            self::COLD_WATER => 'droplet',
            self::HOT_WATER => 'fire',
            self::GAS => 'flame',
            self::HEATING => 'thermometer',
        };
    }
}
