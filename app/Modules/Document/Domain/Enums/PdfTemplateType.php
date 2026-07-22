<?php

declare(strict_types=1);

namespace App\Modules\Document\Domain\Enums;

use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Protocol\Domain\Enums\LegalMode;
use App\Modules\Protocol\Infrastructure\Models\Protocol;

/**
 * D4: PDF template types based on type × initiator_role × legal_mode.
 *
 * 5 canonical templates with Polish headers.
 */
enum PdfTemplateType: string
{
    case BILATERAL_CHECKIN = 'bilateral_checkin';
    case UNILATERAL_TENANT_CHECKIN = 'unilateral_tenant_checkin';
    case CHECKOUT_LANDLORD = 'checkout_landlord';
    case CHECKOUT_TENANT = 'checkout_tenant';
    case CHECKOUT_NO_BASELINE = 'checkout_no_baseline';

    /**
     * Get canonical Polish title for this template type.
     */
    public function polishTitle(): string
    {
        return match ($this) {
            self::BILATERAL_CHECKIN => 'Protokół zdawczo-odbiorczy przy przekazaniu mieszkania',
            self::UNILATERAL_TENANT_CHECKIN => 'Jednostronna dokumentacja stanu mieszkania przy wprowadzeniu się',
            self::CHECKOUT_LANDLORD => 'Protokół zwrotu mieszkania i rozliczenia kaucji',
            self::CHECKOUT_TENANT => 'Dokumentacja zwrotu mieszkania przez najemcę',
            self::CHECKOUT_NO_BASELINE => 'Dokumentacja stanu mieszkania na dzień zwrotu',
        };
    }

    /**
     * Get view name for this template.
     */
    public function viewName(): string
    {
        return match ($this) {
            self::BILATERAL_CHECKIN => 'documents.bilateral_checkin',
            self::UNILATERAL_TENANT_CHECKIN => 'documents.unilateral_tenant_checkin',
            self::CHECKOUT_LANDLORD => 'documents.checkout_landlord',
            self::CHECKOUT_TENANT => 'documents.checkout_tenant',
            self::CHECKOUT_NO_BASELINE => 'documents.checkout_no_baseline',
        };
    }

    /**
     * Determine template type from protocol.
     */
    public static function fromProtocol(Protocol $protocol): self
    {
        // Check-in protocols
        if ($protocol->isCheckIn()) {
            // Bilateral (both sides signed)
            if ($protocol->legal_mode === LegalMode::BILATERAL_COMPLETED) {
                return self::BILATERAL_CHECKIN;
            }

            // Unilateral tenant-initiated
            if ($protocol->initiator_role === ParticipantRole::TENANT) {
                return self::UNILATERAL_TENANT_CHECKIN;
            }

            // Default to bilateral for landlord-initiated
            return self::BILATERAL_CHECKIN;
        }

        // Check-out protocols
        if ($protocol->isCheckOut()) {
            // No baseline reference
            if ($protocol->linked_checkin_id === null) {
                return self::CHECKOUT_NO_BASELINE;
            }

            // Landlord-initiated check-out
            if ($protocol->initiator_role === ParticipantRole::LANDLORD) {
                return self::CHECKOUT_LANDLORD;
            }

            // Tenant-initiated check-out
            return self::CHECKOUT_TENANT;
        }

        // Fallback
        return self::BILATERAL_CHECKIN;
    }

    /**
     * Get description of legal context.
     */
    public function legalContext(): string
    {
        return match ($this) {
            self::BILATERAL_CHECKIN => 'Dokument sporządzony i podpisany przez obie strony umowy najmu.',
            self::UNILATERAL_TENANT_CHECKIN => 'Dokumentacja jednostronna sporządzona przez najemcę. Wynajmujący został powiadomiony o sporządzeniu dokumentacji.',
            self::CHECKOUT_LANDLORD => 'Protokół zwrotu lokalu sporządzony przez wynajmującego z rozliczeniem kaucji.',
            self::CHECKOUT_TENANT => 'Dokumentacja zwrotu lokalu sporządzona przez najemcę.',
            self::CHECKOUT_NO_BASELINE => 'Dokumentacja stanu lokalu bez odniesienia do protokołu wjazdowego.',
        };
    }
}
