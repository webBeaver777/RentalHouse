<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Models;

use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * D2: Entitlement model - grants permission to perform actions.
 */
class Entitlement extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'product_code',
        'allowed_action',
        'quantity_total',
        'quantity_used',
        'valid_until',
        'source_payment_id',
        'activated_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'product_code' => ProductCode::class,
            'allowed_action' => AllowedAction::class,
            'quantity_total' => 'integer',
            'quantity_used' => 'integer',
            'valid_until' => 'datetime',
            'activated_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'source_payment_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(EntitlementUsage::class);
    }

    /**
     * Get remaining quantity.
     */
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity_total - $this->quantity_used);
    }

    /**
     * Check if entitlement is valid (not expired and has remaining uses).
     */
    public function isValid(): bool
    {
        if ($this->expired_at !== null) {
            return false;
        }

        if ($this->valid_until !== null && $this->valid_until->isPast()) {
            return false;
        }

        return $this->remaining_quantity > 0;
    }

    /**
     * Check if entitlement can be used for a specific action.
     */
    public function canBeUsedFor(AllowedAction $action): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        return $this->allowed_action === $action;
    }

    /**
     * Consume one unit of this entitlement.
     */
    public function consume(): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        $this->quantity_used++;
        $this->save();

        return true;
    }

    /**
     * Activate the entitlement.
     */
    public function activate(): self
    {
        $this->activated_at = now();
        $this->save();

        return $this;
    }

    /**
     * Expire the entitlement.
     */
    public function expire(): self
    {
        $this->expired_at = now();
        $this->save();

        return $this;
    }

    /**
     * Scope: Only valid entitlements.
     */
    public function scopeValid($query)
    {
        return $query
            ->whereNull('expired_at')
            ->where(function ($q): void {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            })
            ->whereRaw('quantity_used < quantity_total');
    }

    /**
     * Scope: For a specific action.
     */
    public function scopeForAction($query, AllowedAction $action)
    {
        return $query->where('allowed_action', $action);
    }

    /**
     * Scope: For a specific product.
     */
    public function scopeForProduct($query, ProductCode $product)
    {
        return $query->where('product_code', $product);
    }
}
