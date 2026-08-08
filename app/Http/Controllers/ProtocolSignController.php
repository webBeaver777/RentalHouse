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

        // acceptances.user_agent is varchar(255) — cap defensively at the
        // column width, but store the real value (M10.6 forensic fix: an
        // earlier mb_substr(...,0,64) here was wrong and lost real data).
        // acceptances.device_fingerprint is varchar(64) — that width is the
        // hex length of sha256, i.e. the column is designed to hold a HASH
        // of the client fingerprint, not a truncated prefix of it. Hash it
        // here rather than truncate.
        $userAgent = mb_substr((string) $request->userAgent(), 0, 255);
        $rawFingerprint = (string) $request->input('device_fingerprint');
        $deviceFingerprint = $rawFingerprint !== '' ? hash('sha256', $rawFingerprint) : null;

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
