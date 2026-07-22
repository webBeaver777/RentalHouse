<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Infrastructure\Models;

use App\Modules\Evidence\Infrastructure\Models\Evidence;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProtocolKey extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'protocol_id',
        'name',
        'quantity',
        'description',
        'evidence_id',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected $attributes = [
        'quantity' => 1,
    ];

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    /**
     * Get formatted display string.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->quantity > 1) {
            return "{$this->name} (x{$this->quantity})";
        }

        return $this->name;
    }
}
