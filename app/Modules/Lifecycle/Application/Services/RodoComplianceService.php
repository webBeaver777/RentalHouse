<?php

declare(strict_types=1);

namespace App\Modules\Lifecycle\Application\Services;

use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for RODO (GDPR) compliance operations.
 *
 * Handles data export, retention policies, and user data deletion.
 */
final class RodoComplianceService
{
    private const RETENTION_YEARS = 6; // Polish law requirement for rental documents

    private const ANONYMIZATION_PLACEHOLDER = '[DANE USUNIĘTE]';

    /**
     * Export all user data for GDPR request.
     */
    public function exportUserData(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'protocols' => $this->getUserProtocols($user),
            'properties' => $this->getUserProperties($user),
            'participations' => $this->getUserParticipations($user),
            'exported_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Delete user data (right to be forgotten).
     *
     * Note: Some data must be retained for legal compliance.
     */
    public function deleteUserData(User $user): DeleteResult
    {
        $result = new DeleteResult;

        DB::transaction(function () use ($user, $result): void {
            // Anonymize participation data (cannot delete - legal requirement)
            $this->anonymizeParticipations($user, $result);

            // Delete personal evidence where possible
            $this->deleteUserEvidence($user, $result);

            // Anonymize user account
            $this->anonymizeUser($user, $result);
        });

        Log::info("RODO deletion completed for user {$user->id}", $result->toArray());

        return $result;
    }

    /**
     * Get protocols eligible for deletion (past retention period).
     */
    public function getProtocolsForDeletion(): array
    {
        $cutoffDate = now()->subYears(self::RETENTION_YEARS);

        return Protocol::query()
            ->where('completed_at', '<', $cutoffDate)
            ->orWhere(function ($q) use ($cutoffDate): void {
                $q->whereNull('completed_at')
                    ->where('created_at', '<', $cutoffDate);
            })
            ->pluck('id')
            ->toArray();
    }

    /**
     * Purge protocol data after retention period.
     */
    public function purgeExpiredProtocol(Protocol $protocol): void
    {
        DB::transaction(function () use ($protocol): void {
            // Delete evidence files
            foreach ($protocol->rooms as $room) {
                foreach ($room->items as $item) {
                    foreach ($item->photos as $photo) {
                        $this->deleteEvidenceFile($photo);
                    }
                }
            }

            // Soft delete the protocol (keep metadata for audit)
            $protocol->delete();
        });

        Log::info("Purged expired protocol {$protocol->id}");
    }

    /**
     * Get user's protocols with basic info.
     */
    private function getUserProtocols(User $user): array
    {
        return Protocol::where('created_by_user_id', $user->id)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'type' => $p->type->value,
                'status' => $p->status->value,
                'created_at' => $p->created_at?->toIso8601String(),
                'completed_at' => $p->completed_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Get user's properties.
     */
    private function getUserProperties(User $user): array
    {
        return $user->properties()
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'address' => $p->address,
                'city' => $p->city,
                'created_at' => $p->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Get user's participations in protocols.
     */
    private function getUserParticipations(User $user): array
    {
        return $user->participations()
            ->with('protocol:id,type,status')
            ->get()
            ->map(fn ($p) => [
                'protocol_id' => $p->protocol_id,
                'role' => $p->role->value,
                'is_initiator' => $p->is_initiator,
                'signed_at' => $p->signed_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Anonymize participation data.
     */
    private function anonymizeParticipations(User $user, DeleteResult $result): void
    {
        $participations = $user->participations()->get();

        foreach ($participations as $participation) {
            $participation->update([
                'user_id' => null,
                'invited_email' => self::ANONYMIZATION_PLACEHOLDER,
                'metadata' => array_merge($participation->metadata ?? [], [
                    'anonymized_at' => now()->toIso8601String(),
                    'original_user_id' => $user->id,
                ]),
            ]);
            $result->anonymizedCount++;
        }
    }

    /**
     * Delete user's evidence files where allowed.
     */
    private function deleteUserEvidence(User $user, DeleteResult $result): void
    {
        $evidences = Evidence::where('uploaded_by_user_id', $user->id)
            ->whereHas('protocol', function ($q): void {
                // Only delete from non-completed protocols
                $q->whereNull('completed_at');
            })
            ->get();

        foreach ($evidences as $evidence) {
            $this->deleteEvidenceFile($evidence);
            $evidence->delete();
            $result->deletedFilesCount++;
        }
    }

    /**
     * Delete evidence file from storage.
     */
    private function deleteEvidenceFile(Evidence $evidence): void
    {
        if (Storage::disk($evidence->disk)->exists($evidence->path)) {
            Storage::disk($evidence->disk)->delete($evidence->path);
        }
    }

    /**
     * Anonymize user account.
     */
    private function anonymizeUser(User $user, DeleteResult $result): void
    {
        $user->update([
            'name' => self::ANONYMIZATION_PLACEHOLDER,
            'email' => "deleted_{$user->id}@anonymized.local",
            'password' => bcrypt(str()->random(32)),
            'email_verified_at' => null,
        ]);

        // Revoke all tokens (if using Sanctum)
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        $result->userAnonymized = true;
    }
}
