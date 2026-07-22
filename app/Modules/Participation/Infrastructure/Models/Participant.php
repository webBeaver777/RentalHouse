<?php

namespace App\Modules\Participation\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Participant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'protocol_id',
        'user_id',
        'role',
        'is_initiator',
        'invited_email',
        'invited_at',
        'accepted_at',
        'declined_at',
        'signed_at',
        'signature_data',
        'metadata',
    ];

    protected $attributes = [
        'is_initiator' => false,
    ];

    protected function casts(): array
    {
        return [
            'role' => ParticipantRole::class,
            'is_initiator' => 'boolean',
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'signed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the protocol this participant belongs to.
     */
    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    /**
     * Get the user (if registered).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this participant is the initiator.
     */
    public function isInitiator(): bool
    {
        return $this->is_initiator;
    }

    /**
     * Check if this participant is the counterparty.
     */
    public function isCounterparty(): bool
    {
        return !$this->is_initiator;
    }

    /**
     * Check if invitation is pending.
     */
    public function isPending(): bool
    {
        return $this->invited_at !== null
            && $this->accepted_at === null
            && $this->declined_at === null;
    }

    /**
     * Check if invitation was accepted.
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Check if invitation was declined.
     */
    public function isDeclined(): bool
    {
        return $this->declined_at !== null;
    }

    /**
     * Accept the invitation.
     */
    public function accept(): self
    {
        $this->accepted_at = now();
        $this->declined_at = null;
        $this->save();

        return $this;
    }

    /**
     * Decline the invitation.
     */
    public function decline(): self
    {
        $this->declined_at = now();
        $this->accepted_at = null;
        $this->save();

        return $this;
    }

    /**
     * Check if participant has signed.
     */
    public function hasSigned(): bool
    {
        return $this->signed_at !== null;
    }

    /**
     * Sign the protocol.
     */
    public function sign(?string $signatureData = null): self
    {
        $this->signed_at = now();
        $this->signature_data = $signatureData;
        $this->save();

        return $this;
    }

    /**
     * Get display name (user name or invited email).
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        return $this->invited_email ?? 'Nieznany';
    }

    /**
     * Scope: Only initiators.
     */
    public function scopeInitiators($query)
    {
        return $query->where('is_initiator', true);
    }

    /**
     * Scope: Only counterparties.
     */
    public function scopeCounterparties($query)
    {
        return $query->where('is_initiator', false);
    }

    /**
     * Scope: By role.
     */
    public function scopeWithRole($query, ParticipantRole $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope: Pending invitations.
     */
    public function scopePending($query)
    {
        return $query->whereNotNull('invited_at')
            ->whereNull('accepted_at')
            ->whereNull('declined_at');
    }

    /**
     * Scope: Accepted invitations.
     */
    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }

    /**
     * Scope: Signed participants.
     */
    public function scopeSigned($query)
    {
        return $query->whereNotNull('signed_at');
    }

    /**
     * Scope: Unsigned participants.
     */
    public function scopeUnsigned($query)
    {
        return $query->whereNull('signed_at');
    }
}
