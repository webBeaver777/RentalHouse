<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Application\Services;

use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Domain\Enums\ReferenceMode;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use InvalidArgumentException;

/**
 * Service for managing baseline linking between check-in and check-out protocols.
 *
 * Supports three reference modes:
 * - system_baseline: links to a check-in protocol in the system
 * - uploaded_reference: uses uploaded external documents
 * - no_reference: standalone documentation without reference
 */
final class BaselineService
{
    /**
     * Link a check-out protocol to its baseline check-in.
     */
    public function linkToBaseline(Protocol $checkOut, Protocol $checkIn): void
    {
        $this->validateLinkingPreconditions($checkOut, $checkIn);

        $checkOut->update([
            'linked_checkin_id' => $checkIn->id,
            'reference_mode' => ReferenceMode::SYSTEM_BASELINE,
        ]);

        // Link matching items by catalog_item_id
        $this->linkMatchingItems($checkOut, $checkIn);
    }

    /**
     * Set check-out to use uploaded reference mode.
     */
    public function setUploadedReferenceMode(Protocol $checkOut): void
    {
        if ($checkOut->type !== ProtocolType::CHECK_OUT) {
            throw new InvalidArgumentException('Only check-out protocols can use reference mode');
        }

        $checkOut->update([
            'linked_checkin_id' => null,
            'reference_mode' => ReferenceMode::UPLOADED_REFERENCE,
        ]);
    }

    /**
     * Set check-out to no reference mode.
     */
    public function setNoReferenceMode(Protocol $checkOut): void
    {
        if ($checkOut->type !== ProtocolType::CHECK_OUT) {
            throw new InvalidArgumentException('Only check-out protocols can use reference mode');
        }

        $checkOut->update([
            'linked_checkin_id' => null,
            'reference_mode' => ReferenceMode::NO_REFERENCE,
        ]);
    }

    /**
     * Get side-by-side comparison data for an item.
     */
    public function getItemComparison(ProtocolItem $checkOutItem): ?array
    {
        $baselineItem = $checkOutItem->baselineItem;

        if (! $baselineItem) {
            return null;
        }

        return [
            'checkout' => [
                'id' => $checkOutItem->id,
                'name' => $checkOutItem->display_name,
                'condition' => $checkOutItem->condition_name,
                'condition_state' => $checkOutItem->condition_state,
                'photos' => $checkOutItem->photos()->get()->map(fn ($e) => [
                    'id' => $e->id,
                    'url' => $e->getTemporaryUrl(),
                ])->toArray(),
                'defects' => $checkOutItem->defects ?? [],
            ],
            'baseline' => [
                'id' => $baselineItem->id,
                'name' => $baselineItem->display_name,
                'condition' => $baselineItem->condition_name,
                'condition_state' => $baselineItem->condition_state,
                'photos' => $baselineItem->photos()->get()->map(fn ($e) => [
                    'id' => $e->id,
                    'url' => $e->getTemporaryUrl(),
                ])->toArray(),
                'defects' => $baselineItem->defects ?? [],
            ],
        ];
    }

    /**
     * Get all available check-ins for a property that can be used as baseline.
     */
    public function getAvailableBaselines(Protocol $checkOut): array
    {
        if ($checkOut->type !== ProtocolType::CHECK_OUT) {
            return [];
        }

        return Protocol::query()
            ->where('property_id', $checkOut->property_id)
            ->where('type', ProtocolType::CHECK_IN)
            ->where('status', 'completed')
            ->where('id', '!=', $checkOut->id)
            ->orderByDesc('completed_at')
            ->get()
            ->toArray();
    }

    /**
     * Validate preconditions for baseline linking.
     */
    private function validateLinkingPreconditions(Protocol $checkOut, Protocol $checkIn): void
    {
        if ($checkOut->type !== ProtocolType::CHECK_OUT) {
            throw new InvalidArgumentException('First protocol must be check-out');
        }

        if ($checkIn->type !== ProtocolType::CHECK_IN) {
            throw new InvalidArgumentException('Second protocol must be check-in');
        }

        if ($checkOut->property_id !== $checkIn->property_id) {
            throw new InvalidArgumentException('Both protocols must be for the same property');
        }
    }

    /**
     * Link matching items between check-out and check-in by catalog_item_id.
     */
    private function linkMatchingItems(Protocol $checkOut, Protocol $checkIn): void
    {
        // Get all check-in items indexed by room and catalog_item_id
        $checkInItems = [];
        foreach ($checkIn->rooms as $room) {
            foreach ($room->items as $item) {
                $key = $room->catalog_item_id.'_'.$item->catalog_item_id;
                $checkInItems[$key] = $item;
            }
        }

        // Link check-out items to matching check-in items
        foreach ($checkOut->rooms as $room) {
            foreach ($room->items as $item) {
                $key = $room->catalog_item_id.'_'.$item->catalog_item_id;
                if (isset($checkInItems[$key])) {
                    $item->update(['baseline_item_id' => $checkInItems[$key]->id]);
                }
            }
        }
    }

    /**
     * Create check-out items from baseline check-in items.
     */
    public function createItemsFromBaseline(
        ProtocolRoom $checkOutRoom,
        ProtocolRoom $checkInRoom
    ): void {
        foreach ($checkInRoom->items as $checkInItem) {
            ProtocolItem::create([
                'protocol_room_id' => $checkOutRoom->id,
                'catalog_item_id' => $checkInItem->catalog_item_id,
                'condition_catalog_item_id' => null, // To be filled during check-out
                'custom_name' => $checkInItem->custom_name,
                'quantity' => $checkInItem->quantity,
                'is_valuable' => $checkInItem->is_valuable,
                'requires_serial_plate' => $checkInItem->requires_serial_plate,
                'baseline_item_id' => $checkInItem->id,
                'name_snapshot' => $checkInItem->name_snapshot,
                'sort_order' => $checkInItem->sort_order,
            ]);
        }
    }
}
