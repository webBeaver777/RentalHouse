<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Enums;

/**
 * D2: Allowed actions that entitlements grant.
 *
 * MVP: create_check_in, create_check_out
 * Forward-compatible: monthly_protocol_quota, agency_protocol_quota
 */
enum AllowedAction: string
{
    case CREATE_CHECK_IN = 'create_check_in';
    case CREATE_CHECK_OUT = 'create_check_out';

    // Forward-compatible actions
    case MONTHLY_PROTOCOL_QUOTA = 'monthly_protocol_quota';
    case AGENCY_PROTOCOL_QUOTA = 'agency_protocol_quota';

    public function label(): string
    {
        return match ($this) {
            self::CREATE_CHECK_IN => 'Utworzenie protokołu wjazdowego',
            self::CREATE_CHECK_OUT => 'Utworzenie protokołu wyjazdowego',
            self::MONTHLY_PROTOCOL_QUOTA => 'Miesięczny limit protokołów',
            self::AGENCY_PROTOCOL_QUOTA => 'Limit protokołów agencji',
        };
    }

    /**
     * Get MVP actions only.
     *
     * @return array<self>
     */
    public static function mvpActions(): array
    {
        return [self::CREATE_CHECK_IN, self::CREATE_CHECK_OUT];
    }
}
