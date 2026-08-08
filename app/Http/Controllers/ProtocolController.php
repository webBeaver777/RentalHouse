<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * M10.1, Scenario A slice: check-in protocol creation entry point from
 * an existing property. M10.2 extends show() with rooms/items + catalog
 * pickers (mutations live in ProtocolRoomController/ProtocolItemController).
 * M10.3 extends it further with per-item photo evidence (mutations live in
 * ProtocolItemPhotoController, serving in ProtocolEvidenceController).
 *
 * Backend (Protocol model, ProtocolType, state machine, ProtocolRoom,
 * ProtocolItem, catalog, Evidence) is frozen and already covered by
 * ProtocolTest / ProtocolStateMachineTest / AsymmetricStateMachineTest /
 * ProtocolRoomItemTest / EvidenceTest / EvidenceUploadServiceTest — this
 * controller only wires UI + routes, the same shape as
 * PropertyController::store.
 *
 * NOT in this slice: payment/gate, submit to counterparty, signatures,
 * finalize, PDF, magic-link (M10.4-M10.5).
 */
final class ProtocolController extends Controller
{
    public function create(Request $request): Response
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer'],
        ]);

        $property = Property::where('user_id', Auth::id())
            ->findOrFail($validated['property_id']);

        return Inertia::render('Protocol/Create', [
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'full_address' => $property->full_address,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $property = Property::where('user_id', Auth::id())
            ->findOrFail($validated['property_id']);

        $title = $validated['title'] ?? null;

        $protocol = Protocol::create([
            'property_id' => $property->id,
            'created_by_user_id' => Auth::id(),
            'type' => ProtocolType::CHECK_IN,
            'title' => $title !== null && $title !== ''
                ? $title
                : 'Protokół wjazdu — '.$property->name,
        ]);

        return redirect()->route('protocols.show', $protocol);
    }

    public function show(Protocol $protocol): Response
    {
        abort_unless($protocol->created_by_user_id === Auth::id(), 403);

        $protocol->loadMissing([
            'property',
            'createdBy',
            'rooms.catalogItem',
            'rooms.items.catalogItem',
            'rooms.items.condition',
            'rooms.items.photos',
        ]);

        return Inertia::render('Protocol/Show', [
            'protocol' => [
                'id' => $protocol->id,
                'type' => $protocol->type->value,
                'type_label' => $protocol->type->label(),
                'status' => $protocol->status->value,
                'status_label' => $protocol->status->label(),
                'title' => $protocol->title,
                'is_draft' => $protocol->status === ProtocolStatus::DRAFT,
                'property' => [
                    'name' => $protocol->property->name,
                    'full_address' => $protocol->property->full_address,
                ],
                'initiator_name' => $protocol->createdBy->name,
                'created_at' => $protocol->created_at?->toIso8601String(),
                'rooms' => $protocol->rooms->map(fn (ProtocolRoom $room) => [
                    'id' => $room->id,
                    'display_name' => $room->display_name,
                    'catalog_item_id' => $room->catalog_item_id,
                    'custom_name' => $room->custom_name,
                    'items' => $room->items->map(fn (ProtocolItem $item) => [
                        'id' => $item->id,
                        'display_name' => $item->display_name,
                        'catalog_item_id' => $item->catalog_item_id,
                        'condition_catalog_item_id' => $item->condition_catalog_item_id,
                        'condition_name' => $item->condition_name,
                        'custom_name' => $item->custom_name,
                        'quantity' => $item->quantity,
                        'photos' => $item->photos->map(fn (Evidence $photo) => [
                            'id' => $photo->id,
                            'url' => route('protocols.evidence.show', [$protocol->id, $photo->id]),
                            'original_filename' => $photo->original_filename,
                            'human_size' => $photo->human_size,
                        ])->values()->all(),
                    ])->values()->all(),
                ])->values()->all(),
            ],
            'catalogs' => [
                'room_types' => $this->catalogOptions(CatalogItemType::ROOM),
                'item_templates' => $this->catalogOptions(CatalogItemType::ITEM),
                'condition_states' => $this->catalogOptions(CatalogItemType::CONDITION),
            ],
        ]);
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function catalogOptions(CatalogItemType $type): array
    {
        return CatalogItem::ofType($type)
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CatalogItem $item) => ['id' => $item->id, 'label' => $item->name])
            ->values()
            ->all();
    }
}
