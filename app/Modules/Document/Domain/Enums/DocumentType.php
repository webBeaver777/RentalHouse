<?php

declare(strict_types=1);

namespace App\Modules\Document\Domain\Enums;

enum DocumentType: string
{
    case PROTOCOL_PDF = 'protocol_pdf';
    case SUMMARY_PDF = 'summary_pdf';
    case EVIDENCE_ARCHIVE = 'evidence_archive';
    case COMPARISON_PDF = 'comparison_pdf';

    public function label(): string
    {
        return match ($this) {
            self::PROTOCOL_PDF => 'Protokół PDF',
            self::SUMMARY_PDF => 'Podsumowanie PDF',
            self::EVIDENCE_ARCHIVE => 'Archiwum dowodów',
            self::COMPARISON_PDF => 'Porównanie PDF',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::PROTOCOL_PDF, self::SUMMARY_PDF, self::COMPARISON_PDF => 'application/pdf',
            self::EVIDENCE_ARCHIVE => 'application/zip',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::PROTOCOL_PDF, self::SUMMARY_PDF, self::COMPARISON_PDF => 'pdf',
            self::EVIDENCE_ARCHIVE => 'zip',
        };
    }
}
