<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesDraftProtocolMutation;
use App\Modules\Protocol\Domain\Enums\DefectSeverity;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolDefect;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * M13 step 1, Scenario C slice 2: let the check-out initiator record a
 * deposit withholding (potrącenie) against a changed item, on the draft
 * act. Wires the existing ProtocolDefect model (deposit/defects tracking —
 * see Protocol::getTotalDamageCostAttribute()/getAmountToReturnAttribute(),
 * already summed into PdfGenerationService::calculateDepositData() for the
 * check-out PDF) — no new deposit model invented.
 *
 * Draft-only + initiator guard, same as room/item mutation (see
 * AuthorizesDraftProtocolMutation).
 */
final class ProtocolDefectController extends Controller
{
    use AuthorizesDraftProtocolMutation;

    public function store(Request $request, Protocol $protocol, ProtocolRoom $room, ProtocolItem $item): RedirectResponse
    {
        $this->assertMutable($protocol, 'Potrącenia z kaucji można dodawać tylko w statusie „Szkic”.');
        abort_unless($protocol->type === ProtocolType::CHECK_OUT, 404);
        abort_unless($room->protocol_id === $protocol->id, 404);
        abort_unless($item->protocol_room_id === $room->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'estimated_cost' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        ProtocolDefect::create([
            'protocol_id' => $protocol->id,
            'protocol_item_id' => $item->id,
            'protocol_room_id' => $room->id,
            'title' => $validated['title'],
            'estimated_cost' => $validated['estimated_cost'],
            'severity' => DefectSeverity::MODERATE,
            'is_cost_manual' => true,
        ]);

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Potrącenie zostało dodane.');
    }

    public function destroy(Protocol $protocol, ProtocolRoom $room, ProtocolItem $item, ProtocolDefect $defect): RedirectResponse
    {
        $this->assertMutable($protocol, 'Potrącenia z kaucji można usuwać tylko w statusie „Szkic”.');
        abort_unless($protocol->type === ProtocolType::CHECK_OUT, 404);
        abort_unless($room->protocol_id === $protocol->id, 404);
        abort_unless($item->protocol_room_id === $room->id, 404);
        abort_unless($defect->protocol_item_id === $item->id, 404);

        $defect->delete();

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Potrącenie zostało usunięte.');
    }
}
