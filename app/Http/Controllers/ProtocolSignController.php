<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Acceptance\Application\Actions\SignProtocolAction;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * M10.5, Scenario A slice: initiator signs their own draft-turned-sent
 * check-in protocol. Wires the same frozen SignProtocolAction the guest
 * flow uses (see GuestInvitationController) — no separate gate logic.
 * Once both sides have signed, SignProtocolAction auto-transitions the
 * protocol to signed (never forced here).
 */
final class ProtocolSignController extends Controller
{
    public function store(Request $request, Protocol $protocol, SignProtocolAction $signAction): RedirectResponse
    {
        abort_unless($protocol->created_by_user_id === Auth::id(), 403);

        $participant = $protocol->participants()->where('user_id', Auth::id())->first();
        abort_unless($participant !== null, 404);

        /** @var Participant $participant */

        // acceptances.user_agent AND acceptances.device_fingerprint are both
        // varchar(64) in the frozen schema — real browser UA strings (and a
        // fingerprint built from one) run well past that. Truncate at the
        // wiring layer rather than touch the migration or the frozen action.
        $userAgent = mb_substr((string) $request->userAgent(), 0, 64);
        $deviceFingerprint = mb_substr((string) $request->input('device_fingerprint'), 0, 64);

        try {
            DB::transaction(function () use ($protocol, $participant, $signAction, $request, $userAgent, $deviceFingerprint): void {
                if ($protocol->status === ProtocolStatus::PENDING_COUNTERPARTY) {
                    $protocol->transitionTo(ProtocolStatus::PENDING_SIGNATURES);
                }

                $signAction->execute(
                    $protocol->refresh(),
                    $participant->refresh(),
                    null,
                    $request->ip(),
                    $userAgent,
                    $deviceFingerprint,
                );
            });
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['sign' => $e->getMessage()]);
        }

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Podpisano protokół.');
    }
}
