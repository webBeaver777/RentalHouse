<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Exceptions;

use Exception;

/**
 * D3: Exception thrown when entitlement is required but not available.
 *
 * This is the HARD GATE mechanism.
 */
class EntitlementRequiredException extends Exception
{
    public function __construct(string $message = 'Wymagane jest uprawnienie do wykonania tej akcji.')
    {
        parent::__construct($message);
    }
}
