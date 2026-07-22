<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Domain\Exceptions;

use Exception;

/**
 * Exception thrown when protocol finalization fails.
 */
final class ProtocolFinalizationException extends Exception
{
    public static function wrongType(string $message): self
    {
        return new self($message, 400);
    }

    public static function invalidStatus(string $message): self
    {
        return new self($message, 422);
    }

    public static function missingAcceptance(string $message): self
    {
        return new self($message, 422);
    }

    public static function alreadyFinalized(string $message = 'Protocol is already finalized'): self
    {
        return new self($message, 409);
    }
}
