<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Modules\Notification\Domain\Events\ProtocolSigned;
use App\Modules\Notification\Infrastructure\Notifications\ProtocolSignedNotification;

final class SendProtocolSignedNotification
{
    public function handle(ProtocolSigned $event): void
    {
        $protocol = $event->protocol;
        $signer = $event->signer;

        // Notify other participants
        foreach ($protocol->participants as $participant) {
            if ($participant->id === $signer->id) {
                continue;
            }

            if ($participant->user) {
                $participant->user->notify(
                    new ProtocolSignedNotification($protocol, $signer)
                );
            }
        }
    }
}
