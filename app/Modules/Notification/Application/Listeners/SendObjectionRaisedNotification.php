<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Modules\Notification\Domain\Events\ObjectionRaised;
use App\Modules\Notification\Infrastructure\Notifications\ObjectionRaisedNotification;

final class SendObjectionRaisedNotification
{
    public function handle(ObjectionRaised $event): void
    {
        $protocol = $event->protocol;
        $objection = $event->objection;
        $initiator = $protocol->initiator();

        // Notify the initiator (landlord) about the objection
        if ($initiator?->user) {
            $initiator->user->notify(
                new ObjectionRaisedNotification($protocol, $objection)
            );
        }
    }
}
