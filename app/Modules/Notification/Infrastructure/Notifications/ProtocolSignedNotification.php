<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Notifications;

use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProtocolSignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Protocol $protocol,
        private readonly Participant $signer,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $protocolUrl = url("/protocols/{$this->protocol->id}");
        $signerName = $this->signer->display_name;
        $signerRole = $this->signer->role->label();

        return (new MailMessage)
            ->subject('Protokół został podpisany')
            ->greeting('Witaj!')
            ->line("{$signerName} ({$signerRole}) podpisał protokół.")
            ->action('Zobacz protokół', $protocolUrl)
            ->salutation('Pozdrawiamy, Zespół Rent2Proof');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'protocol_id' => $this->protocol->id,
            'signer_id' => $this->signer->id,
            'signer_name' => $this->signer->display_name,
            'type' => 'protocol_signed',
        ];
    }
}
