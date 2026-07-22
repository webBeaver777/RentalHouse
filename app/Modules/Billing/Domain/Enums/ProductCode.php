<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Enums;

/**
 * D2: Product codes for entitlements.
 *
 * MVP products: WJAZD, WYJAZD
 * Forward-compatible: FULL_CYCLE, TENANT_PROTECTION, LANDLORD_PRO, AGENCY
 */
enum ProductCode: string
{
    // MVP products
    case WJAZD = 'WJAZD';
    case WYJAZD = 'WYJAZD';

    // Forward-compatible products (not active in MVP)
    case FULL_CYCLE = 'FULL_CYCLE';
    case TENANT_PROTECTION = 'TENANT_PROTECTION';
    case LANDLORD_PRO = 'LANDLORD_PRO';
    case AGENCY = 'AGENCY';

    public function label(): string
    {
        return match ($this) {
            self::WJAZD => 'Protokół wjazdowy',
            self::WYJAZD => 'Protokół wyjazdowy',
            self::FULL_CYCLE => 'Pełny cykl najmu',
            self::TENANT_PROTECTION => 'Ochrona najemcy',
            self::LANDLORD_PRO => 'Wynajmujący PRO',
            self::AGENCY => 'Pakiet agencyjny',
        };
    }

    /**
     * Get the allowed action for this product.
     */
    public function allowedAction(): AllowedAction
    {
        return match ($this) {
            self::WJAZD => AllowedAction::CREATE_CHECK_IN,
            self::WYJAZD => AllowedAction::CREATE_CHECK_OUT,
            self::FULL_CYCLE => AllowedAction::CREATE_CHECK_IN, // Includes both
            self::TENANT_PROTECTION => AllowedAction::CREATE_CHECK_IN,
            self::LANDLORD_PRO => AllowedAction::MONTHLY_PROTOCOL_QUOTA,
            self::AGENCY => AllowedAction::AGENCY_PROTOCOL_QUOTA,
        };
    }

    /**
     * Check if this product is available in MVP.
     */
    public function isAvailableInMvp(): bool
    {
        return match ($this) {
            self::WJAZD, self::WYJAZD => true,
            default => false,
        };
    }

    /**
     * Get MVP products only.
     *
     * @return array<self>
     */
    public static function mvpProducts(): array
    {
        return [self::WJAZD, self::WYJAZD];
    }
}
