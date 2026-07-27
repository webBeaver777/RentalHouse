<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Notifications;

use App\Modules\Participation\Infrastructure\Models\InvitationToken;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * G3: Invitation notification.
 *
 * Accepts raw_token (not stored in DB) for URL generation.
 */
class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Protocol $protocol,
        private readonly InvitationToken $token,
        private readonly string $rawToken, // G3: Raw token for URL (not stored)
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // G3: Use raw token for URL
        $invitationUrl = url("/invitation/{$this->rawToken}");
        $propertyAddress = $this->protocol->property?->full_address ?? 'Nieznany adres';
        $protocolType = $this->protocol->type->value === 'check_in' ? 'przekazania' : 'zdania';

        return (new MailMessage)
            ->subject("Zaproszenie do protokołu {$protocolType}")
            ->greeting('Witaj!')
            ->line("Zostałeś zaproszony do udziału w protokole {$protocolType} nieruchomości.")
            ->line("Adres: {$propertyAddress}")
            ->action('Zobacz protokół', $invitationUrl)
            ->line("Link jest ważny do: {$this->token->expires_at->format('d.m.Y H:i')}")
            ->salutation('Pozdrawiamy, Zespół Rent2Proof');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'protocol_id' => $this->protocol->id,
            'token_id' => $this->token->id,
            'type' => 'invitation',
        ];
    }
}
