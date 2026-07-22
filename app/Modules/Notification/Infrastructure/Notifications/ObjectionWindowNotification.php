<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Notifications;

use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ObjectionWindowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Protocol $protocol,
        private readonly int $hoursRemaining,
        private readonly bool $isOpening = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $protocolUrl = url("/protocols/{$this->protocol->id}");
        $propertyAddress = $this->protocol->property?->full_address ?? 'Nieznany adres';

        if ($this->isOpening) {
            return (new MailMessage)
                ->subject('Okno na zgłoszenie zastrzeżeń zostało otwarte')
                ->greeting('Witaj!')
                ->line("Protokół zdania dla nieruchomości {$propertyAddress} został podpisany przez wynajmującego.")
                ->line("Masz {$this->hoursRemaining} godzin na zgłoszenie ewentualnych zastrzeżeń.")
                ->action('Zgłoś zastrzeżenie', $protocolUrl)
                ->line('Jeśli nie zgłosisz zastrzeżeń, protokół zostanie automatycznie zakończony.')
                ->salutation('Pozdrawiamy, Zespół Rent2Proof');
        }

        return (new MailMessage)
            ->subject('Okno na zastrzeżenia zamyka się')
            ->greeting('Witaj!')
            ->line("Pozostało {$this->hoursRemaining} godzin na zgłoszenie zastrzeżeń do protokołu zdania.")
            ->line("Nieruchomość: {$propertyAddress}")
            ->action('Zgłoś zastrzeżenie', $protocolUrl)
            ->line('Po upływie tego czasu protokół zostanie automatycznie zakończony.')
            ->salutation('Pozdrawiamy, Zespół Rent2Proof');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'protocol_id' => $this->protocol->id,
            'hours_remaining' => $this->hoursRemaining,
            'is_opening' => $this->isOpening,
            'type' => 'objection_window',
        ];
    }
}
