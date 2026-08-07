<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesDraftProtocolMutation;
use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * M10.2, Scenario A slice: rooms CRUD on a draft check-in protocol.
 *
 * Backend (ProtocolRoom model, catalog) is frozen and already covered by
 * ProtocolRoomItemTest — this controller only wires mutation endpoints
 * through the existing model/relations, guarded to draft-only + initiator
 * (see AuthorizesDraftProtocolMutation).
 */
final class ProtocolRoomController extends Controller
{
    use AuthorizesDraftProtocolMutation;

    public function store(Request $request, Protocol $protocol): RedirectResponse
    {
        $this->assertMutable($protocol);

        $validated = $request->validate([
            'catalog_item_id' => [
                'required',
                Rule::exists('catalog_items', 'id')
                    ->where('type', CatalogItemType::ROOM->value)
                    ->where('is_active', true),
            ],
            'custom_name' => ['nullable', 'string', 'max:255'],
        ]);

        ProtocolRoom::create([
            'protocol_id' => $protocol->id,
            'catalog_item_id' => $validated['catalog_item_id'],
            'custom_name' => $validated['custom_name'] ?? null,
            'sort_order' => $protocol->rooms()->count() + 1,
        ]);

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Pomieszczenie zostało dodane.');
    }

    public function update(Request $request, Protocol $protocol, ProtocolRoom $room): RedirectResponse
    {
        $this->assertMutable($protocol);
        abort_unless($room->protocol_id === $protocol->id, 404);

        $validated = $request->validate([
            'custom_name' => ['nullable', 'string', 'max:255'],
        ]);

        $room->update(['custom_name' => $validated['custom_name'] ?? null]);

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Pomieszczenie zostało zaktualizowane.');
    }

    public function destroy(Protocol $protocol, ProtocolRoom $room): RedirectResponse
    {
        $this->assertMutable($protocol);
        abort_unless($room->protocol_id === $protocol->id, 404);

        $room->delete();

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Pomieszczenie zostało usunięte.');
    }
}
