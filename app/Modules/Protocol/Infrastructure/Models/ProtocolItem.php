<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Infrastructure\Models;

use App\Modules\Acceptance\Infrastructure\Models\ItemAcceptance;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Participation\Infrastructure\Models\Participant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProtocolItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'protocol_room_id',
        'catalog_item_id',
        'condition_catalog_item_id',
        'custom_name',
        'quantity',
        'is_valuable',
        'requires_serial_plate',
        'condition_notes',
        'condition_state',
        'baseline_item_id',
        'name_snapshot',
        'defects',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_valuable' => 'boolean',
            'requires_serial_plate' => 'boolean',
            'sort_order' => 'integer',
            'defects' => 'array',
            'name_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the baseline item (for check-out items).
     */
    public function baselineItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'baseline_item_id');
    }

    /**
     * Get check-out items linked to this baseline item.
     */
    public function checkoutItems(): HasMany
    {
        return $this->hasMany(self::class, 'baseline_item_id');
    }

    /**
     * Check if this item requires triple-shot photos.
     */
    public function requiresTripleShot(): bool
    {
        return $this->is_valuable;
    }

    /**
     * Check if item has required photos count.
     */
    public function hasRequiredPhotos(): bool
    {
        $count = $this->photos()->count();
        $required = $this->requiresTripleShot() ? 3 : 1;

        return $count >= $required;
    }

    /**
     * Check if serial plate photo is required but missing.
     */
    public function isMissingSerialPlatePhoto(): bool
    {
        if (! $this->requires_serial_plate) {
            return false;
        }

        // Check if any photo has serial_plate tag in metadata
        return ! $this->photos()
            ->whereJsonContains('metadata->tags', 'serial_plate')
            ->exists();
    }

    protected $attributes = [
        'quantity' => 1,
    ];

    /**
     * Get the room this item belongs to.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(ProtocolRoom::class, 'protocol_room_id');
    }

    /**
     * Get the catalog item (item template) this is based on.
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    /**
     * Get the condition catalog item.
     */
    public function condition(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'condition_catalog_item_id');
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

    /**
     * Get the condition display name.
     */
    public function getConditionNameAttribute(): ?string
    {
        return $this->condition?->getTranslation('name', app()->getLocale());
    }

    /**
     * Check if item has any defects.
     */
    public function hasDefects(): bool
    {
        return ! empty($this->defects);
    }

    /**
     * Get all evidences (photos, documents) for this item.
     */
    public function evidences(): MorphMany
    {
        return $this->morphMany(Evidence::class, 'evidenceable')->orderBy('sort_order');
    }

    /**
     * Get only photos for this item.
     */
    public function photos(): MorphMany
    {
        return $this->morphMany(Evidence::class, 'evidenceable')
            ->where('type', 'photo')
            ->orderBy('sort_order');
    }

    /**
     * Get all acceptance records for this item.
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(ItemAcceptance::class, 'protocol_item_id');
    }

    /**
     * Get acceptance for specific participant.
     */
    public function getAcceptanceFor(Participant $participant): ?ItemAcceptance
    {
        return $this->acceptances()->where('participant_id', $participant->id)->first();
    }

    /**
     * Check if item is accepted by participant.
     */
    public function isAcceptedBy(Participant $participant): bool
    {
        return $this->getAcceptanceFor($participant)?->isAccepted() ?? false;
    }

    /**
     * Check if item is disputed by participant.
     */
    public function isDisputedBy(Participant $participant): bool
    {
        return $this->getAcceptanceFor($participant)?->isDisputed() ?? false;
    }

    /**
     * Check if all participants have accepted this item.
     */
    public function isFullyAccepted(): bool
    {
        $protocol = $this->room->protocol;
        $participantCount = $protocol->participants()->count();

        if ($participantCount === 0) {
            return false;
        }

        $acceptedCount = $this->acceptances()->accepted()->count();

        return $acceptedCount === $participantCount;
    }

    /**
     * Check if any participant has disputed this item.
     */
    public function hasDisputes(): bool
    {
        return $this->acceptances()->disputed()->exists();
    }
}
