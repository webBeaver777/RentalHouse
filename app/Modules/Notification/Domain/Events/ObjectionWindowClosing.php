<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Events;

use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired 24h before objection window closes.
 */
final class ObjectionWindowClosing
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Protocol $protocol,
        public readonly int $hoursRemaining,
    ) {}
}
