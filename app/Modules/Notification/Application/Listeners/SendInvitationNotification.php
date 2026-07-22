<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Modules\Notification\Domain\Events\ParticipantInvited;
use App\Modules\Notification\Infrastructure\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;

final class SendInvitationNotification
{
    public function handle(ParticipantInvited $event): void
    {
        Notification::route('mail', $event->token->email)
            ->notify(new InvitationNotification($event->protocol, $event->token));
    }
}
