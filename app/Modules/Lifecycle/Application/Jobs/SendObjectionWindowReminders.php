<?php

declare(strict_types=1);

namespace App\Modules\Lifecycle\Application\Jobs;

use App\Modules\Notification\Domain\Events\ObjectionWindowClosing;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job that sends reminders when objection window is about to close.
 *
 * Sends reminder 24h before window closes.
 */
final class SendObjectionWindowReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const REMINDER_HOURS_BEFORE = 24;

    public function handle(): void
    {
        $reminderThreshold = now()->addHours(self::REMINDER_HOURS_BEFORE);

        $protocols = Protocol::query()
            ->where('type', ProtocolType::CHECK_OUT)
            ->where('status', ProtocolStatus::SIGNED)
            ->whereNotNull('objection_window_ends_at')
            ->where('objection_window_ends_at', '>', now())
            ->where('objection_window_ends_at', '<=', $reminderThreshold)
            ->whereNull('metadata->objection_reminder_sent')
            ->get();

        foreach ($protocols as $protocol) {
            $this->sendReminder($protocol);
        }

        Log::info("Sent {$protocols->count()} objection window reminders");
    }

    private function sendReminder(Protocol $protocol): void
    {
        $hoursRemaining = (int) now()->diffInHours($protocol->objection_window_ends_at);

        ObjectionWindowClosing::dispatch($protocol, $hoursRemaining);

        // Mark reminder as sent
        $metadata = $protocol->metadata ?? [];
        $metadata['objection_reminder_sent'] = now()->toIso8601String();
        $protocol->update(['metadata' => $metadata]);

        Log::info("Sent objection window reminder for protocol {$protocol->id}");
    }
}
