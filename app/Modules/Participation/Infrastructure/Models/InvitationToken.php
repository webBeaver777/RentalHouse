<?php

declare(strict_types=1);

namespace App\Modules\Participation\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * G3: Invitation token model.
 *
 * Raw tokens are NEVER stored - only SHA-256 hash.
 * Expiry is configurable via lifecycle.invitation_token_hours.
 */
class InvitationToken extends Model
{
    use HasUuids;

    protected $fillable = [
        'participant_id',
        'token_hash', // G3: Only store hash, never raw token
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
     * G3: Scope: Find by token string (hashes the token for lookup).
     */
    public function scopeByToken($query, string $token)
    {
        return $query->where('token_hash', hash('sha256', $token));
    }

    /**
     * G3: Create token with hash storage.
     *
     * Returns the raw token (to be sent to user), stores only hash.
     */
    public static function createForParticipant(
        Participant $participant,
        string $email,
        ?int $expiryHours = null
    ): array {
        $expiryHours = $expiryHours ?? (int) config('lifecycle.invitation_token_hours', 72);
        $rawToken = bin2hex(random_bytes(32)); // 64 character hex string

        $token = self::create([
            'participant_id' => $participant->id,
            'token_hash' => hash('sha256', $rawToken),
            'email' => $email,
            'expires_at' => now()->addHours($expiryHours),
        ]);

        return [
            'token' => $token,
            'raw_token' => $rawToken, // Send this to user, never store
        ];
    }

    /**
     * G3: Find valid token by raw token string.
     */
    public static function findByRawToken(string $rawToken): ?self
    {
        return self::byToken($rawToken)->valid()->first();
    }
}
