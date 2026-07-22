<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Enums;

/**
 * D1: Payment statuses for Przelewy24 integration.
 */
enum PaymentStatus: string
{
    case PENDING = 'pending';
    case STARTED = 'started';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Oczekująca',
            self::STARTED => 'Rozpoczęta',
            self::PAID => 'Opłacona',
            self::FAILED => 'Nieudana',
            self::CANCELLED => 'Anulowana',
            self::REFUNDED => 'Zwrócona',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::PAID, self::FAILED, self::CANCELLED, self::REFUNDED => true,
            default => false,
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::PAID;
    }
}
