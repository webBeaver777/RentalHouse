<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Events;

use App\Modules\Participation\Infrastructure\Models\InvitationToken;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ParticipantInvited
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Protocol $protocol,
        public readonly Participant $participant,
        public readonly InvitationToken $token,
    ) {}
}
