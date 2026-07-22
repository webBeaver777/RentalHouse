<?php

declare(strict_types=1);

namespace App\Modules\Acceptance\Infrastructure\Models;

use App\Modules\Acceptance\Domain\Enums\AcceptanceStatus;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemAcceptance extends Model
{
    use HasFactory;

    protected $fillable = [
        'protocol_id',
        'protocol_item_id',
        'participant_id',
        'status',
        'dispute_reason',
        'resolved_at',
        'resolution_notes',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => AcceptanceStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Get the protocol.
     */
    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    /**
     * Get the protocol item being accepted.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ProtocolItem::class, 'protocol_item_id');
    }

    /**
     * Get the participant making the acceptance decision.
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * Check if pending.
     */
    public function isPending(): bool
    {
        return $this->status === AcceptanceStatus::PENDING;
    }

    /**
     * Check if accepted.
     */
    public function isAccepted(): bool
    {
        return $this->status === AcceptanceStatus::ACCEPTED;
    }

    /**
     * Check if disputed.
     */
    public function isDisputed(): bool
    {
        return $this->status === AcceptanceStatus::DISPUTED;
    }

    /**
     * Check if resolved (either accepted or dispute resolved).
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Accept the item.
     */
    public function accept(): self
    {
        $this->status = AcceptanceStatus::ACCEPTED;
        $this->resolved_at = now();
        $this->dispute_reason = null;
        $this->save();

        return $this;
    }

    /**
     * Dispute the item.
     */
    public function dispute(string $reason): self
    {
        $this->status = AcceptanceStatus::DISPUTED;
        $this->dispute_reason = $reason;
        $this->resolved_at = null;
        $this->save();

        return $this;
    }

    /**
     * Resolve a dispute.
     */
    public function resolveDispute(string $notes): self
    {
        $this->resolved_at = now();
        $this->resolution_notes = $notes;
        $this->save();

        return $this;
    }

    /**
     * Scope: Only pending.
     */
    public function scopePending($query)
    {
        return $query->where('status', AcceptanceStatus::PENDING);
    }

    /**
     * Scope: Only accepted.
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', AcceptanceStatus::ACCEPTED);
    }

    /**
     * Scope: Only disputed.
     */
    public function scopeDisputed($query)
    {
        return $query->where('status', AcceptanceStatus::DISPUTED);
    }

    /**
     * Scope: Unresolved disputes.
     */
    public function scopeUnresolvedDisputes($query)
    {
        return $query->disputed()->whereNull('resolved_at');
    }

    /**
     * Scope: By participant.
     */
    public function scopeByParticipant($query, Participant $participant)
    {
        return $query->where('participant_id', $participant->id);
    }

    /**
     * Scope: By protocol.
     */
    public function scopeByProtocol($query, Protocol|string $protocol)
    {
        $protocolId = $protocol instanceof Protocol ? $protocol->id : $protocol;

        return $query->where('protocol_id', $protocolId);
    }
}
