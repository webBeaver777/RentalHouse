<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Models;

use App\Modules\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * D1: Payment model for Przelewy24 integration.
 */
class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'p24_session_id',
        'p24_order_id',
        'p24_transaction_id',
        'product_code',
        'amount',
        'currency',
        'description',
        'status',
        'started_at',
        'paid_at',
        'failed_at',
        'failure_reason',
        'verification_sign',
        'is_verified',
        'buyer_email',
        'buyer_name',
        'is_sandbox',
    ];

    protected function casts(): array
    {
        return [
            'product_code' => ProductCode::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'started_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'is_verified' => 'boolean',
            'is_sandbox' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entitlement(): HasOne
    {
        return $this->hasOne(Entitlement::class, 'source_payment_id');
    }

    /**
     * Get amount in PLN (from grosze).
     */
    public function getAmountInPlnAttribute(): float
    {
        return $this->amount / 100;
    }

    /**
     * Check if payment is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::PAID && $this->is_verified;
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return in_array($this->status, [PaymentStatus::PENDING, PaymentStatus::STARTED]);
    }

    /**
     * Mark payment as started (user redirected to P24).
     */
    public function markAsStarted(): self
    {
        $this->status = PaymentStatus::STARTED;
        $this->started_at = now();
        $this->save();

        return $this;
    }

    /**
     * Mark payment as paid (webhook received).
     */
    public function markAsPaid(string $transactionId): self
    {
        $this->status = PaymentStatus::PAID;
        $this->p24_transaction_id = $transactionId;
        $this->paid_at = now();
        $this->save();

        return $this;
    }

    /**
     * Mark payment as failed.
     */
    public function markAsFailed(?string $reason = null): self
    {
        $this->status = PaymentStatus::FAILED;
        $this->failed_at = now();
        $this->failure_reason = $reason;
        $this->save();

        return $this;
    }

    /**
     * Mark as verified after signature check.
     */
    public function markAsVerified(string $sign): self
    {
        $this->verification_sign = $sign;
        $this->is_verified = true;
        $this->save();

        return $this;
    }

    /**
     * Scope: Pending payments.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [PaymentStatus::PENDING, PaymentStatus::STARTED]);
    }

    /**
     * Scope: Successful payments.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', PaymentStatus::PAID)->where('is_verified', true);
    }

    /**
     * Scope: By session ID.
     */
    public function scopeBySessionId($query, string $sessionId)
    {
        return $query->where('p24_session_id', $sessionId);
    }
}
