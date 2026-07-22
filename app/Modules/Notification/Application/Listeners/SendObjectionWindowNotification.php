<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Modules\Notification\Domain\Events\ObjectionWindowClosing;
use App\Modules\Notification\Domain\Events\ObjectionWindowOpened;
use App\Modules\Notification\Infrastructure\Notifications\ObjectionWindowNotification;

final class SendObjectionWindowNotification
{
    public function handleOpened(ObjectionWindowOpened $event): void
    {
        $protocol = $event->protocol;
        $counterparty = $protocol->counterparty();

        if ($counterparty?->user) {
            $counterparty->user->notify(
                new ObjectionWindowNotification(
                    $protocol,
                    $protocol->remainingObjectionHours() ?? 72,
                    isOpening: true
                )
            );
        }
    }

    public function handleClosing(ObjectionWindowClosing $event): void
    {
        $protocol = $event->protocol;
        $counterparty = $protocol->counterparty();

        if ($counterparty?->user) {
            $counterparty->user->notify(
                new ObjectionWindowNotification(
                    $protocol,
                    $event->hoursRemaining,
                    isOpening: false
                )
            );
        }
    }
}
