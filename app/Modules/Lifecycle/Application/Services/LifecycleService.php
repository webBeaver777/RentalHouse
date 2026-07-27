<?php

declare(strict_types=1);

namespace App\Modules\Lifecycle\Application\Services;

use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * D8: Lifecycle management service.
 *
 * Handles:
 * - access_expires_at = paid_at + 12 months (configurable)
 * - retention_until = access_expires_at + 3 years (configurable)
 * - Archive transition
 * - Soft-delete after retention_until
 * - Audit trail
 */
final class LifecycleService
{
    /**
     * Set lifecycle dates when protocol is paid.
     *
     * Called from EntitlementService when entitlement is consumed.
     */
    public function onPayment(Protocol $protocol, Carbon $paidAt): void
    {
        $accessMonths = config('lifecycle.access_months', 12);
        $retentionYears = config('lifecycle.retention_years', 3);

        $accessExpiresAt = $paidAt->copy()->addMonths($accessMonths);
        $retentionUntil = $accessExpiresAt->copy()->addYears($retentionYears);

        $protocol->paid_at = $paidAt;
        $protocol->access_expires_at = $accessExpiresAt;
        $protocol->retention_until = $retentionUntil;
        $protocol->save();

        // Audit: record lifecycle start
        InspectionEvent::record(
            $protocol,
            InspectionEventType::PROTOCOL_STATUS_CHANGED,
            'system',
            null,
            [
                'action' => 'lifecycle_started',
                'paid_at' => $paidAt->toIso8601String(),
                'access_expires_at' => $accessExpiresAt->toIso8601String(),
                'retention_until' => $retentionUntil->toIso8601String(),
            ]
        );

        Log::info('Lifecycle started for protocol', [
            'protocol_id' => $protocol->id,
            'paid_at' => $paidAt->toIso8601String(),
            'access_expires_at' => $accessExpiresAt->toIso8601String(),
            'retention_until' => $retentionUntil->toIso8601String(),
        ]);
    }

    /**
     * Check if user has access to protocol.
     */
    public function hasAccess(Protocol $protocol): bool
    {
        // If never paid, no access
        if ($protocol->paid_at === null) {
            return false;
        }

        // If access hasn't expired
        if ($protocol->access_expires_at === null) {
            return true; // Infinite access if not set
        }

        return now()->lessThan($protocol->access_expires_at);
    }

    /**
     * Archive expired protocols.
     *
     * Protocols past access_expires_at but before retention_until.
     *
     * @return int Number of archived protocols
     */
    public function archiveExpiredProtocols(): int
    {
        $count = 0;
        $now = now();

        Protocol::query()
            ->whereNotNull('access_expires_at')
            ->where('access_expires_at', '<=', $now)
            ->where('status', '!=', ProtocolStatus::ARCHIVED)
            ->whereNull('deleted_at')
            ->chunk(100, function (Collection $protocols) use (&$count): void {
                foreach ($protocols as $protocol) {
                    $this->archive($protocol);
                    $count++;
                }
            });

        Log::info("Archived {$count} expired protocols");

        return $count;
    }

    /**
     * Archive a single protocol.
     */
    public function archive(Protocol $protocol): void
    {
        $previousStatus = $protocol->status;

        DB::transaction(function () use ($protocol, $previousStatus): void {
            $protocol->status = ProtocolStatus::ARCHIVED;
            $protocol->save();

            // Audit: record archive
            InspectionEvent::record(
                $protocol,
                InspectionEventType::PROTOCOL_STATUS_CHANGED,
                'system',
                null,
                [
                    'action' => 'archived',
                    'previous_status' => $previousStatus->value,
                    'reason' => 'access_expired',
                ]
            );
        });

        Log::info('Protocol archived', [
            'protocol_id' => $protocol->id,
            'previous_status' => $previousStatus->value,
        ]);
    }

    /**
     * Purge protocols past retention period.
     *
     * Soft-deletes protocols past retention_until.
     *
     * @return int Number of purged protocols
     */
    public function purgeExpiredProtocols(): int
    {
        $count = 0;
        $now = now();

        Protocol::query()
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', $now)
            ->whereNull('deleted_at')
            ->chunk(100, function (Collection $protocols) use (&$count): void {
                foreach ($protocols as $protocol) {
                    $this->purge($protocol);
                    $count++;
                }
            });

        Log::info("Purged {$count} protocols past retention period");

        return $count;
    }

    /**
     * Soft-delete a single protocol (retention expired).
     */
    public function purge(Protocol $protocol): void
    {
        DB::transaction(function () use ($protocol): void {
            // Audit: record purge before deletion
            InspectionEvent::record(
                $protocol,
                InspectionEventType::PROTOCOL_STATUS_CHANGED,
                'system',
                null,
                [
                    'action' => 'purged',
                    'reason' => 'retention_expired',
                    'retention_until' => $protocol->retention_until?->toIso8601String(),
                ]
            );

            // Soft delete
            $protocol->delete();
        });

        Log::info('Protocol purged (soft-deleted)', [
            'protocol_id' => $protocol->id,
            'retention_until' => $protocol->retention_until?->toIso8601String(),
        ]);
    }

    /**
     * Get protocols expiring soon (for notification).
     *
     * @param  int  $days  Days before expiration to warn
     */
    public function getExpiringProtocols(int $days = 30): Collection
    {
        return Protocol::query()
            ->whereNotNull('access_expires_at')
            ->where('access_expires_at', '>', now())
            ->where('access_expires_at', '<=', now()->addDays($days))
            ->whereNull('deleted_at')
            ->get();
    }

    /**
     * Extend access period (for renewal).
     */
    public function extendAccess(Protocol $protocol, int $months): void
    {
        $previousExpires = $protocol->access_expires_at;
        $retentionYears = config('lifecycle.retention_years', 3);

        // Extend from current expiration or now, whichever is later
        $baseDate = $previousExpires && $previousExpires->isFuture()
            ? $previousExpires
            : now();

        $newExpires = $baseDate->copy()->addMonths($months);
        $newRetention = $newExpires->copy()->addYears($retentionYears);

        $protocol->access_expires_at = $newExpires;
        $protocol->retention_until = $newRetention;
        $protocol->save();

        // Audit: record extension
        InspectionEvent::record(
            $protocol,
            InspectionEventType::PROTOCOL_STATUS_CHANGED,
            'system',
            null,
            [
                'action' => 'access_extended',
                'previous_expires' => $previousExpires?->toIso8601String(),
                'new_expires' => $newExpires->toIso8601String(),
                'months_extended' => $months,
            ]
        );

        Log::info('Protocol access extended', [
            'protocol_id' => $protocol->id,
            'previous_expires' => $previousExpires?->toIso8601String(),
            'new_expires' => $newExpires->toIso8601String(),
        ]);
    }
}
