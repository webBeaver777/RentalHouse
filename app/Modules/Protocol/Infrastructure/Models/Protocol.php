<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Infrastructure\Models;

use App\Modules\Acceptance\Infrastructure\Models\ProtocolObjection;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\LegalMode;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Domain\Enums\ReferenceMode;
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
        'initiator_role',
        'counterparty_role',
        'legal_mode',
        'status',
        'title',
        'description',
        'scheduled_at',
        'completed_at',
        'act_issued_at',
        'objection_window_ends_at',
        'reference_mode',
        'linked_checkin_id',
        'locale',
        'property_declaration_type',
        'deposit_amount',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProtocolType::class,
            'initiator_role' => ParticipantRole::class,
            'counterparty_role' => ParticipantRole::class,
            'legal_mode' => LegalMode::class,
            'status' => ProtocolStatus::class,
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'act_issued_at' => 'datetime',
            'objection_window_ends_at' => 'datetime',
            'reference_mode' => ReferenceMode::class,
            'deposit_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the linked check-in protocol (for check-out).
     */
    public function linkedCheckin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_checkin_id');
    }

    /**
     * Get check-outs linked to this check-in.
     */
    public function linkedCheckouts(): HasMany
    {
        return $this->hasMany(self::class, 'linked_checkin_id');
    }

    /**
     * Check if this is a check-in protocol.
     */
    public function isCheckIn(): bool
    {
        return $this->type === ProtocolType::CHECK_IN;
    }

    /**
     * Check if this is a check-out protocol.
     */
    public function isCheckOut(): bool
    {
        return $this->type === ProtocolType::CHECK_OUT;
    }

    /**
     * Check if objection window is still open.
     */
    public function isObjectionWindowOpen(): bool
    {
        if (! $this->objection_window_ends_at) {
            return false;
        }

        return now()->isBefore($this->objection_window_ends_at);
    }

    /**
     * Get remaining objection window hours.
     */
    public function remainingObjectionHours(): ?int
    {
        if (! $this->isObjectionWindowOpen()) {
            return null;
        }

        return (int) now()->diffInHours($this->objection_window_ends_at);
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
        if (! $this->status->canTransitionTo($newStatus)) {
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

    /**
     * Get all meters in this protocol.
     */
    public function meters(): HasMany
    {
        return $this->hasMany(ProtocolMeter::class)->orderBy('sort_order');
    }

    /**
     * Get all keys in this protocol.
     */
    public function keys(): HasMany
    {
        return $this->hasMany(ProtocolKey::class)->orderBy('sort_order');
    }

    /**
     * Get all defects in this protocol.
     */
    public function defects(): HasMany
    {
        return $this->hasMany(ProtocolDefect::class)->orderBy('sort_order');
    }

    /**
     * Get all reference documents (for uploaded_reference mode).
     */
    public function referenceDocuments(): HasMany
    {
        return $this->hasMany(ReferenceDocument::class)->orderBy('sort_order');
    }

    /**
     * Get all objections (for check-out protocols).
     */
    public function objections(): HasMany
    {
        return $this->hasMany(ProtocolObjection::class);
    }

    /**
     * Check if protocol has unresolved objections.
     */
    public function hasUnresolvedObjections(): bool
    {
        return $this->objections()->unresolved()->exists();
    }

    /**
     * Get total damage cost from defects.
     */
    public function getTotalDamageCostAttribute(): float
    {
        return (float) $this->defects()
            ->whereNotNull('estimated_cost')
            ->sum('estimated_cost');
    }

    /**
     * Get amount to return after deducting damages.
     */
    public function getAmountToReturnAttribute(): float
    {
        $deposit = (float) ($this->deposit_amount ?? 0);
        $damage = $this->total_damage_cost;

        return max(0, $deposit - $damage);
    }
}
