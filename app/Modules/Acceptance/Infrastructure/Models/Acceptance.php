<?php

declare(strict_types=1);

namespace App\Modules\Acceptance\Infrastructure\Models;

use App\Modules\Acceptance\Domain\Enums\AcceptanceType;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * G2: Acceptance model.
 *
 * One row = one acceptance action by a participant.
 */
class Acceptance extends Model
{
    use HasUuids;

    protected $fillable = [
        'protocol_id',
        'accepted_by_role',
        'accepted_by_user_id',
        'counterparty_participation_id',
        'acceptance_type',
        'consent_text_snapshot',
        'accepted_at',
        'ip_address',
        'user_agent',
        'device_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'accepted_by_role' => ParticipantRole::class,
            'acceptance_type' => AcceptanceType::class,
            'accepted_at' => 'datetime',
        ];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'counterparty_participation_id');
    }

    /**
     * Check if this is an initiator's acceptance.
     */
    public function isInitiatorAcceptance(): bool
    {
        return $this->counterparty_participation_id === null;
    }

    /**
     * Record a new acceptance.
     */
    public static function recordAcceptance(
        Protocol $protocol,
        ParticipantRole $role,
        AcceptanceType $type,
        ?int $userId = null,
        ?int $participationId = null,
        ?string $consentText = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceFingerprint = null
    ): self {
        return self::create([
            'protocol_id' => $protocol->id,
            'accepted_by_role' => $role,
            'accepted_by_user_id' => $userId,
            'counterparty_participation_id' => $participationId,
            'acceptance_type' => $type,
            'consent_text_snapshot' => $consentText,
            'accepted_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_fingerprint' => $deviceFingerprint,
        ]);
    }

    /**
     * Scope: By role.
     */
    public function scopeByRole($query, ParticipantRole $role)
    {
        return $query->where('accepted_by_role', $role);
    }

    /**
     * Scope: By type.
     */
    public function scopeOfType($query, AcceptanceType $type)
    {
        return $query->where('acceptance_type', $type);
    }
}
