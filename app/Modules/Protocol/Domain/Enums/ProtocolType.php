<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Domain\Enums;

enum ProtocolType: string
{
    case CHECK_IN = 'check_in';
    case CHECK_OUT = 'check_out';
    case PERIODIC = 'periodic';

    public function label(): string
    {
        return match ($this) {
            self::CHECK_IN => 'Protokół zdawczo-odbiorczy (wprowadzenie)',
            self::CHECK_OUT => 'Protokół zdawczo-odbiorczy (wyprowadzenie)',
            self::PERIODIC => 'Protokół okresowy',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::CHECK_IN => 'Wprowadzenie',
            self::CHECK_OUT => 'Wyprowadzenie',
            self::PERIODIC => 'Okresowy',
        };
    }
}
