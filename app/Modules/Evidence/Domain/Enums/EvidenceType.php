<?php

namespace App\Modules\Evidence\Domain\Enums;

enum EvidenceType: string
{
    case PHOTO = 'photo';
    case DOCUMENT = 'document';
    case VIDEO = 'video';

    public function label(): string
    {
        return match ($this) {
            self::PHOTO => 'Zdjęcie',
            self::DOCUMENT => 'Dokument',
            self::VIDEO => 'Wideo',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PHOTO => 'heroicon-o-photo',
            self::DOCUMENT => 'heroicon-o-document',
            self::VIDEO => 'heroicon-o-video-camera',
        };
    }

    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::PHOTO => ['image/jpeg', 'image/png', 'image/webp', 'image/heic'],
            self::DOCUMENT => ['application/pdf'],
            self::VIDEO => ['video/mp4', 'video/quicktime'],
        };
    }
}
