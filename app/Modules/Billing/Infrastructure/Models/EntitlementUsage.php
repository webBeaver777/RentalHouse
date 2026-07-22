<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Models;

use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D2: Tracks consumption of entitlements.
 *
 * Links entitlement_id ↔ protocol_id (inspection_id per ТЗ).
 */
class EntitlementUsage extends Model
{
    use HasUuids;

    protected $fillable = [
        'entitlement_id',
        'protocol_id',
        'consumed_at',
        'action',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
            'action' => AllowedAction::class,
        ];
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(Entitlement::class);
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    /**
     * Create usage record for consuming an entitlement.
     */
    public static function recordConsumption(
        Entitlement $entitlement,
        Protocol $protocol,
        AllowedAction $action,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'entitlement_id' => $entitlement->id,
            'protocol_id' => $protocol->id,
            'consumed_at' => now(),
            'action' => $action,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
