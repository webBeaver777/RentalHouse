<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Infrastructure\Models;

use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProtocolRoom extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'protocol_id',
        'catalog_item_id',
        'custom_name',
        'description',
        'name_snapshot',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'name_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the protocol this room belongs to.
     */
    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    /**
     * Get the catalog item (room template) this room is based on.
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    /**
     * Get all items in this room.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProtocolItem::class)->orderBy('sort_order');
    }

    /**
     * Get the display name (custom name, snapshot, or catalog name).
     *
     * Priority: custom_name > name_snapshot > live catalog
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->custom_name) {
            return $this->custom_name;
        }

        // G5: Use frozen snapshot if available
        if ($this->name_snapshot) {
            $locale = app()->getLocale();

            return $this->name_snapshot[$locale]
                ?? $this->name_snapshot['pl']
                ?? array_values($this->name_snapshot)[0]
                ?? '';
        }

        return $this->catalogItem?->getTranslation('name', app()->getLocale()) ?? '';
    }
}
