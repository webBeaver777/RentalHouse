<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * D6: Protocol comment model.
 *
 * Allows counterparty to comment on protocol elements.
 */
class ProtocolComment extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'protocol_id',
        'author_role',
        'author_user_id',
        'body',
        'commentable_type',
        'commentable_id',
        'ip_address',
        'user_agent',
        'device_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'author_role' => ParticipantRole::class,
        ];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * Get the commentable entity.
     */
    public function commentable()
    {
        if (! $this->commentable_type || ! $this->commentable_id) {
            return null;
        }

        $model = match ($this->commentable_type) {
            'protocol' => Protocol::class,
            'room' => ProtocolRoom::class,
            'item' => ProtocolItem::class,
            'defect' => ProtocolDefect::class,
            default => null,
        };

        if ($model === null) {
            return null;
        }

        return $model::find($this->commentable_id);
    }

    /**
     * Check if comment is on protocol level.
     */
    public function isProtocolLevel(): bool
    {
        return $this->commentable_type === null || $this->commentable_type === 'protocol';
    }

    /**
     * Scope: Comments by a role.
     */
    public function scopeByRole($query, ParticipantRole $role)
    {
        return $query->where('author_role', $role);
    }

    /**
     * Scope: Comments for a specific entity.
     */
    public function scopeForEntity($query, string $type, string $id)
    {
        return $query->where('commentable_type', $type)
            ->where('commentable_id', $id);
    }
}
