<?php

declare(strict_types=1);

namespace App\Modules\Participation\Domain\Enums;

/**
 * G1: Full spectrum of participation statuses (§11).
 */
enum ParticipationStatus: string
{
    case NOT_SENT = 'not_sent';
    case SENT = 'sent';
    case DELIVERY_FAILED = 'delivery_failed';
    case OPENED = 'opened';
    case VIEWED_SECTIONS = 'viewed_sections';
    case COMMENTED = 'commented';
    case PHOTO_ADDED = 'photo_added';
    case ACCEPTED = 'accepted';
    case ACCEPTED_WITH_COMMENTS = 'accepted_with_comments';
    case OBJECTED = 'objected';
    case DECLINED_TO_SIGN = 'declined_to_sign';
    case EXPIRED_NO_RESPONSE = 'expired_no_response';

    public function label(): string
    {
        return match ($this) {
            self::NOT_SENT => 'Nie wysłano',
            self::SENT => 'Wysłano',
            self::DELIVERY_FAILED => 'Dostarczenie nieudane',
            self::OPENED => 'Otwarto',
            self::VIEWED_SECTIONS => 'Przeglądano sekcje',
            self::COMMENTED => 'Dodano komentarz',
            self::PHOTO_ADDED => 'Dodano zdjęcie',
            self::ACCEPTED => 'Zaakceptowano',
            self::ACCEPTED_WITH_COMMENTS => 'Zaakceptowano z uwagami',
            self::OBJECTED => 'Zgłoszono zastrzeżenia',
            self::DECLINED_TO_SIGN => 'Odmówiono podpisu',
            self::EXPIRED_NO_RESPONSE => 'Wygasło bez odpowiedzi',
        };
    }

    /**
     * Check if this is a terminal (final) status.
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::ACCEPTED,
            self::ACCEPTED_WITH_COMMENTS,
            self::OBJECTED,
            self::DECLINED_TO_SIGN,
            self::EXPIRED_NO_RESPONSE => true,
            default => false,
        };
    }

    /**
     * Check if participation is active (can still respond).
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::SENT,
            self::OPENED,
            self::VIEWED_SECTIONS,
            self::COMMENTED,
            self::PHOTO_ADDED => true,
            default => false,
        };
    }

    /**
     * Get allowed transitions from this status.
     *
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::NOT_SENT => [self::SENT, self::DELIVERY_FAILED],
            self::SENT => [self::OPENED, self::DELIVERY_FAILED, self::EXPIRED_NO_RESPONSE],
            self::DELIVERY_FAILED => [self::SENT],
            self::OPENED => [
                self::VIEWED_SECTIONS,
                self::COMMENTED,
                self::PHOTO_ADDED,
                self::ACCEPTED,
                self::ACCEPTED_WITH_COMMENTS,
                self::OBJECTED,
                self::DECLINED_TO_SIGN,
                self::EXPIRED_NO_RESPONSE,
            ],
            self::VIEWED_SECTIONS => [
                self::COMMENTED,
                self::PHOTO_ADDED,
                self::ACCEPTED,
                self::ACCEPTED_WITH_COMMENTS,
                self::OBJECTED,
                self::DECLINED_TO_SIGN,
                self::EXPIRED_NO_RESPONSE,
            ],
            self::COMMENTED, self::PHOTO_ADDED => [
                self::COMMENTED,
                self::PHOTO_ADDED,
                self::ACCEPTED,
                self::ACCEPTED_WITH_COMMENTS,
                self::OBJECTED,
                self::DECLINED_TO_SIGN,
                self::EXPIRED_NO_RESPONSE,
            ],
            default => [], // Final states have no transitions
        };
    }
}
