<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesDraftProtocolMutation;
use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * M10.2, Scenario A slice: item CRUD inside a room, on a draft check-in
 * protocol.
 *
 * Backend (ProtocolItem model, catalog) is frozen and already covered by
 * ProtocolRoomItemTest — this controller only wires mutation endpoints
 * through the existing model/relations, guarded to draft-only + initiator
 * (see AuthorizesDraftProtocolMutation).
 */
final class ProtocolItemController extends Controller
{
    use AuthorizesDraftProtocolMutation;

    public function store(Request $request, Protocol $protocol, ProtocolRoom $room): RedirectResponse
    {
        $this->assertMutable($protocol);
        abort_unless($room->protocol_id === $protocol->id, 404);

        $validated = $request->validate([
            'catalog_item_id' => [
                'required',
                Rule::exists('catalog_items', 'id')
                    ->where('type', CatalogItemType::ITEM->value)
                    ->where('is_active', true),
            ],
            'condition_catalog_item_id' => [
                'nullable',
                Rule::exists('catalog_items', 'id')
                    ->where('type', CatalogItemType::CONDITION->value)
                    ->where('is_active', true),
            ],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'custom_name' => ['nullable', 'string', 'max:255'],
        ]);

        ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $validated['catalog_item_id'],
            'condition_catalog_item_id' => $validated['condition_catalog_item_id'] ?? null,
            'quantity' => $validated['quantity'] ?? 1,
            'custom_name' => $validated['custom_name'] ?? null,
            'sort_order' => $room->items()->count() + 1,
        ]);

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Pozycja została dodana.');
    }

    public function update(Request $request, Protocol $protocol, ProtocolRoom $room, ProtocolItem $item): RedirectResponse
    {
        $this->assertMutable($protocol);
        abort_unless($room->protocol_id === $protocol->id, 404);
        abort_unless($item->protocol_room_id === $room->id, 404);

        $validated = $request->validate([
            'condition_catalog_item_id' => [
                'nullable',
                Rule::exists('catalog_items', 'id')
                    ->where('type', CatalogItemType::CONDITION->value)
                    ->where('is_active', true),
            ],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'custom_name' => ['nullable', 'string', 'max:255'],
        ]);

        $item->update([
            'condition_catalog_item_id' => $validated['condition_catalog_item_id'] ?? null,
            'quantity' => $validated['quantity'] ?? 1,
            'custom_name' => $validated['custom_name'] ?? null,
        ]);

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Pozycja została zaktualizowana.');
    }

    public function destroy(Protocol $protocol, ProtocolRoom $room, ProtocolItem $item): RedirectResponse
    {
        $this->assertMutable($protocol);
        abort_unless($room->protocol_id === $protocol->id, 404);
        abort_unless($item->protocol_room_id === $room->id, 404);

        $item->delete();

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Pozycja została usunięta.');
    }
}
