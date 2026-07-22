<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Modules\Notification\Domain\Events\ProtocolCompleted;
use App\Modules\Notification\Infrastructure\Notifications\ProtocolCompletedNotification;

final class SendProtocolCompletedNotification
{
    public function handle(ProtocolCompleted $event): void
    {
        $protocol = $event->protocol;

        foreach ($protocol->participants as $participant) {
            if ($participant->user) {
                $participant->user->notify(
                    new ProtocolCompletedNotification($protocol)
                );
            }
        }
    }
}
