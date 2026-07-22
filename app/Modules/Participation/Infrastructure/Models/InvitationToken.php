<?php

declare(strict_types=1);

namespace App\Modules\Participation\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationToken extends Model
{
    use HasUuids;

    protected $fillable = [
        'participant_id',
        'token',
        'email',
        'expires_at',
        'used_at',
        'revoked_at',
        'used_ip',
        'used_user_agent',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * Check if token is valid (not expired, used, or revoked).
     */
    public function isValid(): bool
    {
        if ($this->used_at !== null) {
            return false;
        }

        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if token has been used.
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Check if token has been revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Check if token has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Mark token as used.
     */
    public function markAsUsed(?string $ip = null, ?string $userAgent = null): self
    {
        $this->used_at = now();
        $this->used_ip = $ip;
        $this->used_user_agent = $userAgent;
        $this->save();

        return $this;
    }

    /**
     * Revoke the token.
     */
    public function revoke(): self
    {
        $this->revoked_at = now();
        $this->save();

        return $this;
    }

    /**
     * Scope: Only valid tokens.
     */
    public function scopeValid($query)
    {
        return $query->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope: Find by token string.
     */
    public function scopeByToken($query, string $token)
    {
        return $query->where('token', $token);
    }
}
