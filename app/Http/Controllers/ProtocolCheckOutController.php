<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Protocol\Application\Services\BaselineService;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * M12, Scenario C slice 1: create a check-out protocol linked to a
 * completed check-in as its baseline. Wires two frozen mechanisms —
 * BaselineService::linkToBaseline() (sets linked_checkin_id +
 * reference_mode=SYSTEM_BASELINE) and ::createItemsFromBaseline() (copies
 * each baseline item into a fresh check-out item with condition left null
 * "to be filled during check-out", per its own docblock) — no new linking
 * or copying logic invented here. Rooms are mirrored 1:1 rather than
 * retro-matched (BaselineService::linkMatchingItems() matches by
 * catalog_item_id on already-existing check-out rooms; here the check-out
 * has no rooms yet, so we create them fresh, one per baseline room, and
 * populate each immediately from its baseline counterpart).
 *
 * C1 stops at draft: no participant/signing/entitlement wiring here — that
 * (and finalize/act issuance) is C2, same as the frozen IssueCheckOutAction
 * documents itself.
 */
final class ProtocolCheckOutController extends Controller
{
    public function store(Protocol $protocol, BaselineService $baselineService): RedirectResponse
    {
        abort_unless($protocol->created_by_user_id === Auth::id(), 403);
        abort_unless($protocol->type === ProtocolType::CHECK_IN, 404);
        abort_unless(
            $protocol->status === ProtocolStatus::COMPLETED,
            422,
            'Protokół wyjazdu można utworzyć tylko z zakończonego protokołu wjazdu.'
        );

        /** @var User $user */
        $user = Auth::user();

        $protocol->loadMissing(['property', 'rooms.items']);

        // Same user, same role they had on the baseline check-in — mirrors
        // the M11 role-flip principle (derive from data, don't hardcode).
        $initiatorRole = $protocol->getParticipantRole($user) ?? ParticipantRole::LANDLORD;
        $counterpartyRole = $initiatorRole->opposite();

        $checkOut = DB::transaction(function () use ($protocol, $user, $initiatorRole, $counterpartyRole, $baselineService): Protocol {
            $checkOut = Protocol::create([
                'property_id' => $protocol->property_id,
                'created_by_user_id' => $user->id,
                'type' => ProtocolType::CHECK_OUT,
                'initiator_role' => $initiatorRole,
                'counterparty_role' => $counterpartyRole,
                'title' => 'Protokół wyjazdu — '.$protocol->property->name,
            ]);

            $baselineService->linkToBaseline($checkOut, $protocol);

            foreach ($protocol->rooms as $baselineRoom) {
                /** @var ProtocolRoom $baselineRoom */
                $checkOutRoom = ProtocolRoom::create([
                    'protocol_id' => $checkOut->id,
                    'catalog_item_id' => $baselineRoom->catalog_item_id,
                    'custom_name' => $baselineRoom->custom_name,
                    'sort_order' => $baselineRoom->sort_order,
                ]);

                $baselineService->createItemsFromBaseline($checkOutRoom, $baselineRoom);
            }

            return $checkOut;
        });

        return redirect()->route('protocols.show', $checkOut)
            ->with('status', 'Protokół wyjazdu został utworzony na podstawie protokołu wjazdu.');
    }
}
