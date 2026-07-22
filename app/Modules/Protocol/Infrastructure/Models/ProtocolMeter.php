<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Infrastructure\Models;

use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Protocol\Domain\Enums\MeterType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProtocolMeter extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'protocol_id',
        'type',
        'meter_number',
        'reading',
        'unit',
        'evidence_id',
        'notes',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => MeterType::class,
            'reading' => 'decimal:3',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    /**
     * Get unit with fallback to type default.
     */
    public function getDisplayUnitAttribute(): string
    {
        return $this->unit ?? $this->type->unit();
    }

    /**
     * Get formatted reading with unit.
     */
    public function getFormattedReadingAttribute(): string
    {
        return number_format($this->reading, 3, ',', ' ').' '.$this->display_unit;
    }
}
