<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Infrastructure\Models;

use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Protocol\Domain\Enums\DefectSeverity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProtocolDefect extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'protocol_id',
        'protocol_item_id',
        'protocol_room_id',
        'title',
        'description',
        'severity',
        'marker_x',
        'marker_y',
        'estimated_cost',
        'is_cost_manual',
        'evidence_id',
        'defect_catalog_item_id',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'severity' => DefectSeverity::class,
            'marker_x' => 'decimal:2',
            'marker_y' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'is_cost_manual' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProtocolItem::class, 'protocol_item_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ProtocolRoom::class, 'protocol_room_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    public function defectCatalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'defect_catalog_item_id');
    }

    /**
     * Check if defect has position marker.
     */
    public function hasMarker(): bool
    {
        return $this->marker_x !== null && $this->marker_y !== null;
    }

    /**
     * Get marker position as array.
     */
    public function getMarkerPosition(): ?array
    {
        if (! $this->hasMarker()) {
            return null;
        }

        return [
            'x' => (float) $this->marker_x,
            'y' => (float) $this->marker_y,
        ];
    }

    /**
     * Check if cost was manually entered.
     */
    public function hasCost(): bool
    {
        return $this->estimated_cost !== null;
    }

    /**
     * Get formatted cost.
     */
    public function getFormattedCostAttribute(): ?string
    {
        if (! $this->hasCost()) {
            return null;
        }

        return number_format($this->estimated_cost, 2, ',', ' ').' PLN';
    }
}
