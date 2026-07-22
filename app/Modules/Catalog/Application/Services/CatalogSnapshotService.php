<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Services;

use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Localization\Infrastructure\Models\Locale;

/**
 * Service for creating immutable snapshots of catalog items.
 *
 * When a protocol is sealed, catalog item names must be frozen
 * to prevent changes from affecting historical documents.
 */
final class CatalogSnapshotService
{
    /**
     * Create a snapshot of a catalog item's translatable name.
     *
     * Returns a JSON-encoded array of all translations at the moment of capture.
     * This snapshot is stored with the protocol item and never changes.
     *
     * @param  CatalogItem  $item  The catalog item to snapshot
     * @return array<string, string> Associative array of locale => name
     */
    public function snapshotName(CatalogItem $item): array
    {
        $snapshot = [];

        // Get all active locales
        $locales = Locale::getActive();

        foreach ($locales as $locale) {
            $translation = $item->getTranslation('name', $locale->code, false);
            if ($translation !== null && $translation !== '') {
                $snapshot[$locale->code] = $translation;
            }
        }

        // Always ensure we have the default locale
        $defaultLocale = Locale::getDefault();
        if ($defaultLocale && ! isset($snapshot[$defaultLocale->code])) {
            $fallback = $item->getTranslation('name', $defaultLocale->code, false);
            if ($fallback !== null && $fallback !== '') {
                $snapshot[$defaultLocale->code] = $fallback;
            }
        }

        return $snapshot;
    }

    /**
     * Create a snapshot of multiple catalog items.
     *
     * @param  iterable<CatalogItem>  $items
     * @return array<int, array{id: int, type: string, name_snapshot: array<string, string>}>
     */
    public function snapshotMany(iterable $items): array
    {
        $snapshots = [];

        foreach ($items as $item) {
            $snapshots[] = [
                'id' => $item->id,
                'type' => $item->type->value,
                'name_snapshot' => $this->snapshotName($item),
            ];
        }

        return $snapshots;
    }

    /**
     * Get the display name from a snapshot for a given locale.
     *
     * Falls back to default locale (pl) if requested locale is not available.
     *
     * @param  array<string, string>  $snapshot  The name snapshot
     * @param  string  $locale  The requested locale code
     * @return string The display name
     */
    public function getNameFromSnapshot(array $snapshot, string $locale = 'pl'): string
    {
        // Try requested locale first
        if (isset($snapshot[$locale]) && $snapshot[$locale] !== '') {
            return $snapshot[$locale];
        }

        // Fallback to Polish (default)
        if (isset($snapshot['pl']) && $snapshot['pl'] !== '') {
            return $snapshot['pl'];
        }

        // Last resort: return first available translation
        foreach ($snapshot as $name) {
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    /**
     * Create a full snapshot including description.
     *
     * @return array{name: array<string, string>, description: array<string, string>}
     */
    public function snapshotFull(CatalogItem $item): array
    {
        $nameSnapshot = $this->snapshotName($item);
        $descriptionSnapshot = [];

        $locales = Locale::getActive();

        foreach ($locales as $locale) {
            $translation = $item->getTranslation('description', $locale->code, false);
            if ($translation !== null && $translation !== '') {
                $descriptionSnapshot[$locale->code] = $translation;
            }
        }

        return [
            'name' => $nameSnapshot,
            'description' => $descriptionSnapshot,
        ];
    }
}
