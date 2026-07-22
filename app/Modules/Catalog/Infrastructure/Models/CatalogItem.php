<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class CatalogItem extends Model
{
    use HasTranslations, SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'description',
        'parent_id',
        'sort_order',
        'is_active',
    ];

    public array $translatable = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'type' => CatalogItemType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get parent catalog item.
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get child catalog items.
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, CatalogItemType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get only active items.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get root items (no parent).
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }
}
