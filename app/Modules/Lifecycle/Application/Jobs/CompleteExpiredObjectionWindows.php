<?php

declare(strict_types=1);

namespace App\Modules\Lifecycle\Application\Jobs;

use App\Modules\Notification\Domain\Events\ProtocolCompleted;
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
 * Job that auto-completes check-out protocols when objection window expires.
 *
 * Run via scheduler: every 15 minutes.
 */
final class CompleteExpiredObjectionWindows implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $protocols = Protocol::query()
            ->where('type', ProtocolType::CHECK_OUT)
            ->whereIn('status', [ProtocolStatus::SIGNED, ProtocolStatus::DISPUTED])
            ->whereNotNull('objection_window_ends_at')
            ->where('objection_window_ends_at', '<=', now())
            ->get();

        foreach ($protocols as $protocol) {
            $this->completeProtocol($protocol);
        }

        Log::info("Completed {$protocols->count()} protocols with expired objection windows");
    }

    private function completeProtocol(Protocol $protocol): void
    {
        // §21 canon: objection is a one-sided act recorded alongside the protocol,
        // never a precondition for finalization. An unresolved objection stays
        // attached to the completed protocol (and the final document) instead of
        // blocking completion — resolving it is not required.
        if ($protocol->hasUnresolvedObjections()) {
            Log::info("Protocol {$protocol->id} has unresolved objection(s); completing anyway, objection remains attached");
        }

        try {
            $protocol->complete();
            ProtocolCompleted::dispatch($protocol);

            Log::info("Auto-completed protocol {$protocol->id} after objection window expired");
        } catch (\Exception $e) {
            Log::error("Failed to auto-complete protocol {$protocol->id}: {$e->getMessage()}");
        }
    }
}
