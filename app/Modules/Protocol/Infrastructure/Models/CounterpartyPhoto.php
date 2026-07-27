<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * D6: Counterparty photo model.
 *
 * Photos uploaded by the counterparty during review.
 *
 * @property-read Protocol|null $protocol
 * @property-read User|null $uploadedBy
 */
class CounterpartyPhoto extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'protocol_id',
        'uploaded_by_role',
        'uploaded_by_user_id',
        'room_id',
        'item_id',
        'path',
        'disk',
        'filename',
        'mime_type',
        'size',
        'hash',
        'captured_at',
        'uploaded_at',
        'device_info',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_by_role' => ParticipantRole::class,
            'size' => 'integer',
            'captured_at' => 'datetime',
            'uploaded_at' => 'datetime',
        ];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ProtocolRoom::class, 'room_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProtocolItem::class, 'item_id');
    }

    /**
     * Get the URL for this photo.
     */
    public function getUrlAttribute(): ?string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Check if photo exists on disk.
     */
    public function existsOnDisk(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /**
     * Verify hash integrity.
     */
    public function verifyHash(): bool
    {
        if (! $this->existsOnDisk()) {
            return false;
        }

        $content = Storage::disk($this->disk)->get($this->path);
        $currentHash = hash('sha256', $content);

        return hash_equals($this->hash, $currentHash);
    }

    /**
     * Scope: Photos uploaded by counterparty role.
     */
    public function scopeByRole($query, ParticipantRole $role)
    {
        return $query->where('uploaded_by_role', $role);
    }

    /**
     * Scope: Photos for a specific room.
     */
    public function scopeForRoom($query, string $roomId)
    {
        return $query->where('room_id', $roomId);
    }

    /**
     * Scope: Photos for a specific item.
     */
    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }
}
