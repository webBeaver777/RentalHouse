<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Domain\Enums;

/**
 * G4: Reference document types.
 */
enum ReferenceDocumentType: string
{
    case PDF = 'pdf';
    case PHOTO = 'photo';
    case PAPER_PROTOCOL = 'paper_protocol';
    case LEASE_CONTRACT = 'lease_contract';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PDF => 'Dokument PDF',
            self::PHOTO => 'Zdjęcie',
            self::PAPER_PROTOCOL => 'Protokół papierowy',
            self::LEASE_CONTRACT => 'Umowa najmu',
            self::OTHER => 'Inny dokument',
        };
    }

    /**
     * Get allowed MIME types for this document type.
     */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::PDF, self::PAPER_PROTOCOL, self::LEASE_CONTRACT => ['application/pdf'],
            self::PHOTO => ['image/jpeg', 'image/png', 'image/webp'],
            self::OTHER => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
        };
    }
}
