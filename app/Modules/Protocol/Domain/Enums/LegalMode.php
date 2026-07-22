<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Domain\Enums;

/**
 * Legal mode determines PDF template and consent/signature texts.
 *
 * Different modes may have different legal requirements and document formats.
 */
enum LegalMode: string
{
    case STANDARD = 'standard';
    case BILATERAL = 'bilateral';
    case BILATERAL_COMPLETED = 'bilateral_completed';
    case UNILATERAL_LANDLORD = 'unilateral_landlord';
    case UNILATERAL_TENANT = 'unilateral_tenant';
    case UNILATERAL_NO_RESPONSE = 'unilateral_no_response';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD => 'Standardowy (dwustronny)',
            self::BILATERAL => 'Dwustronny z pełnymi podpisami',
            self::BILATERAL_COMPLETED => 'Dwustronny zakończony',
            self::UNILATERAL_LANDLORD => 'Jednostronny (wynajmujący)',
            self::UNILATERAL_TENANT => 'Jednostronny (najemca)',
            self::UNILATERAL_NO_RESPONSE => 'Jednostronny (brak odpowiedzi)',
        };
    }

    /**
     * Check if this mode requires both parties to sign for validity.
     */
    public function requiresBothSignatures(): bool
    {
        return match ($this) {
            self::STANDARD, self::BILATERAL, self::BILATERAL_COMPLETED => true,
            self::UNILATERAL_LANDLORD, self::UNILATERAL_TENANT, self::UNILATERAL_NO_RESPONSE => false,
        };
    }
}
