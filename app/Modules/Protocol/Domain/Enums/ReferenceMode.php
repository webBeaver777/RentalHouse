<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Domain\Enums;

/**
 * Reference mode determines how baseline is handled for check-out protocols.
 *
 * §P1.3: Check-out can work with:
 * - System baseline (linked check-in from the system)
 * - Uploaded reference (external document/photos)
 * - No reference (standalone documentation)
 */
enum ReferenceMode: string
{
    case SYSTEM_BASELINE = 'system_baseline';
    case UPLOADED_REFERENCE = 'uploaded_reference';
    case NO_REFERENCE = 'no_reference';

    public function label(): string
    {
        return match ($this) {
            self::SYSTEM_BASELINE => 'Bazowy protokół z systemu',
            self::UPLOADED_REFERENCE => 'Wgrane dokumenty referencyjne',
            self::NO_REFERENCE => 'Bez dokumentów referencyjnych',
        };
    }

    /**
     * Check if this mode requires a linked check-in.
     */
    public function requiresLinkedCheckin(): bool
    {
        return $this === self::SYSTEM_BASELINE;
    }

    /**
     * Check if this mode supports uploaded reference documents.
     */
    public function supportsUploadedReference(): bool
    {
        return $this === self::UPLOADED_REFERENCE;
    }
}
