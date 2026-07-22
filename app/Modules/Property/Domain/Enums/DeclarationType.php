<?php

declare(strict_types=1);

namespace App\Modules\Property\Domain\Enums;

enum DeclarationType: string
{
    case OWNER = 'owner';
    case TENANT_DECLARED = 'tenant_declared';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Właściciel',
            self::TENANT_DECLARED => 'Zgłoszony przez najemcę',
        };
    }
}
