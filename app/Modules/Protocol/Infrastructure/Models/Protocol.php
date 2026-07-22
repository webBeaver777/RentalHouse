<?php

namespace App\Modules\Protocol\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Protocol extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'property_id',
        'created_by_user_id',
        'type',
        'status',
        'title',
        'description',
        'scheduled_at',
        'completed_at',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProtocolType::class,
            'status' => ProtocolStatus::class,
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the property this protocol belongs to.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the user who created this protocol.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get all rooms in this protocol.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(ProtocolRoom::class)->orderBy('sort_order');
    }

    /**
     * Get all participants in this protocol.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /**
     * Get the initiator participant.
     */
    public function initiator(): ?Participant
    {
        return $this->participants()->where('is_initiator', true)->first();
    }

    /**
     * Get the counterparty participant.
     */
    public function counterparty(): ?Participant
    {
        return $this->participants()->where('is_initiator', false)->first();
    }

    /**
     * Get landlord participant.
     */
    public function landlord(): ?Participant
    {
        return $this->participants()->where('role', ParticipantRole::LANDLORD)->first();
    }

    /**
     * Get tenant participant.
     */
    public function tenant(): ?Participant
    {
        return $this->participants()->where('role', ParticipantRole::TENANT)->first();
    }

    /**
     * Check if user is a participant in this protocol.
     */
    public function hasParticipant(User $user): bool
    {
        return $this->participants()->where('user_id', $user->id)->exists();
    }

    /**
     * Get participant role for a user.
     */
    public function getParticipantRole(User $user): ?ParticipantRole
    {
        $participant = $this->participants()->where('user_id', $user->id)->first();
        return $participant?->role;
    }

    /**
     * Check if protocol can be edited.
     */
    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Check if protocol is in final state.
     */
    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    /**
     * Transition to a new status.
     *
     * @throws InvalidArgumentException
     */
    public function transitionTo(ProtocolStatus $newStatus): self
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                "Cannot transition from {$this->status->value} to {$newStatus->value}"
            );
        }

        $this->status = $newStatus;

        if ($newStatus === ProtocolStatus::COMPLETED) {
            $this->completed_at = now();
        }

        $this->save();

        return $this;
    }

    /**
     * Check if transition to given status is allowed.
     */
    public function canTransitionTo(ProtocolStatus $status): bool
    {
        return $this->status->canTransitionTo($status);
    }

    /**
     * Get allowed transitions from current state.
     */
    public function allowedTransitions(): array
    {
        return $this->status->allowedTransitions();
    }

    /**
     * Submit protocol to counterparty for review.
     */
    public function submitToCounterparty(): self
    {
        return $this->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
    }

    /**
     * Request signatures from all parties.
     */
    public function requestSignatures(): self
    {
        return $this->transitionTo(ProtocolStatus::PENDING_SIGNATURES);
    }

    /**
     * Mark protocol as signed.
     */
    public function markAsSigned(): self
    {
        return $this->transitionTo(ProtocolStatus::SIGNED);
    }

    /**
     * Complete the protocol.
     */
    public function complete(): self
    {
        return $this->transitionTo(ProtocolStatus::COMPLETED);
    }

    /**
     * Cancel the protocol.
     */
    public function cancel(): self
    {
        return $this->transitionTo(ProtocolStatus::CANCELLED);
    }

    /**
     * Revert to draft (from pending_counterparty only).
     */
    public function revertToDraft(): self
    {
        return $this->transitionTo(ProtocolStatus::DRAFT);
    }

    /**
     * Check if all participants have signed.
     */
    public function allParticipantsSigned(): bool
    {
        $total = $this->participants()->count();
        $signed = $this->participants()->signed()->count();

        return $total > 0 && $total === $signed;
    }

    /**
     * Check if any participant has signed.
     */
    public function anyParticipantSigned(): bool
    {
        return $this->participants()->signed()->exists();
    }

    /**
     * Get count of unsigned participants.
     */
    public function unsignedParticipantsCount(): int
    {
        return $this->participants()->unsigned()->count();
    }

    /**
     * Check and auto-transition to signed state if all have signed.
     */
    public function checkAndTransitionToSigned(): bool
    {
        if ($this->status === ProtocolStatus::PENDING_SIGNATURES && $this->allParticipantsSigned()) {
            $this->markAsSigned();
            return true;
        }

        return false;
    }
}
