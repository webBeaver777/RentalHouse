<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D7: Persistent timeline event for inspection.
 */
class InspectionEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'protocol_id',
        'actor_role',
        'actor_user_id',
        'event_type',
        'payload',
        'occurred_at',
        'ip_address',
        'user_agent',
        'device_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => InspectionEventType::class,
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Record a new event.
     */
    public static function record(
        Protocol $protocol,
        InspectionEventType $eventType,
        ?string $actorRole = null,
        ?int $actorUserId = null,
        ?array $payload = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceFingerprint = null
    ): self {
        return self::create([
            'protocol_id' => $protocol->id,
            'actor_role' => $actorRole ?? 'system',
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'payload' => $payload,
            'occurred_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_fingerprint' => $deviceFingerprint,
        ]);
    }

    /**
     * Get formatted timeline entry for PDF.
     */
    public function getTimelineEntryAttribute(): array
    {
        return [
            'time' => $this->occurred_at->format('d.m.Y H:i'),
            'event' => $this->event_type->shortLabel(),
            'actor' => $this->getActorLabel(),
        ];
    }

    /**
     * Get actor label (role or "System").
     */
    private function getActorLabel(): string
    {
        if ($this->actor_role === 'system') {
            return 'System';
        }

        try {
            $role = ParticipantRole::from($this->actor_role);

            return $role->label();
        } catch (\ValueError) {
            return ucfirst($this->actor_role);
        }
    }

    /**
     * Scope: Events for a protocol ordered by time.
     */
    public function scopeForProtocol($query, Protocol $protocol)
    {
        return $query->where('protocol_id', $protocol->id)
            ->orderBy('occurred_at', 'asc');
    }

    /**
     * Scope: Events of specific type.
     */
    public function scopeOfType($query, InspectionEventType $type)
    {
        return $query->where('event_type', $type);
    }
}
