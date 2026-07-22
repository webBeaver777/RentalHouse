<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Notifications;

use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProtocolCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Protocol $protocol,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $protocolUrl = url("/protocols/{$this->protocol->id}");
        $protocolType = $this->protocol->type->value === 'check_in' ? 'przekazania' : 'zdania';
        $propertyAddress = $this->protocol->property?->full_address ?? 'Nieznany adres';

        return (new MailMessage)
            ->subject("Protokół {$protocolType} został zakończony")
            ->greeting('Witaj!')
            ->line("Protokół {$protocolType} dla nieruchomości pod adresem {$propertyAddress} został zakończony.")
            ->line('Dokument PDF jest dostępny do pobrania.')
            ->action('Pobierz protokół', $protocolUrl)
            ->salutation('Pozdrawiamy, Zespół Rent2Proof');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'protocol_id' => $this->protocol->id,
            'type' => 'protocol_completed',
        ];
    }
}
