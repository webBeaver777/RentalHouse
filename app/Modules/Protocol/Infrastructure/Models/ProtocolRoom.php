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
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
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
     * Get the display name (custom name or catalog name).
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->custom_name) {
            return $this->custom_name;
        }

        return $this->catalogItem?->getTranslation('name', app()->getLocale()) ?? '';
    }
}
