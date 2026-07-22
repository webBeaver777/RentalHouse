<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Notifications;

use App\Modules\Acceptance\Infrastructure\Models\ProtocolObjection;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ObjectionRaisedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Protocol $protocol,
        private readonly ProtocolObjection $objection,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $protocolUrl = url("/protocols/{$this->protocol->id}");
        $propertyAddress = $this->protocol->property?->full_address ?? 'Nieznany adres';
        $objectorName = $this->objection->participant->display_name;

        return (new MailMessage)
            ->subject('Zgłoszono zastrzeżenie do protokołu')
            ->greeting('Witaj!')
            ->line("{$objectorName} zgłosił zastrzeżenie do protokołu zdania.")
            ->line("Nieruchomość: {$propertyAddress}")
            ->line("Powód: {$this->objection->reason}")
            ->action('Zobacz szczegóły', $protocolUrl)
            ->salutation('Pozdrawiamy, Zespół Rent2Proof');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'protocol_id' => $this->protocol->id,
            'objection_id' => $this->objection->id,
            'type' => 'objection_raised',
        ];
    }
}
