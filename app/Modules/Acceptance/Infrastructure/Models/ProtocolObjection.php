<?php

declare(strict_types=1);

namespace App\Modules\Acceptance\Infrastructure\Models;

use App\Modules\Acceptance\Domain\Enums\ObjectionOutcome;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtocolObjection extends Model
{
    use HasUuids;

    protected $fillable = [
        'protocol_id',
        'participant_id',
        'reason',
        'item_ids',
        'raised_at',
        'resolved_at',
        'resolution_notes',
        'resolution_outcome',
    ];

    protected function casts(): array
    {
        return [
            'item_ids' => 'array',
            'raised_at' => 'datetime',
            'resolved_at' => 'datetime',
            'resolution_outcome' => ObjectionOutcome::class,
        ];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * Check if objection is resolved.
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Check if objection is pending.
     */
    public function isPending(): bool
    {
        return $this->resolved_at === null;
    }

    /**
     * Resolve objection.
     */
    public function resolve(ObjectionOutcome $outcome, ?string $notes = null): self
    {
        $this->resolved_at = now();
        $this->resolution_outcome = $outcome;
        $this->resolution_notes = $notes;
        $this->save();

        return $this;
    }

    /**
     * Scope: Unresolved objections.
     */
    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope: Resolved objections.
     */
    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
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
