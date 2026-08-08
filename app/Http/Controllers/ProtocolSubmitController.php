<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesDraftProtocolMutation;
use App\Modules\Billing\Domain\Exceptions\EntitlementRequiredException;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Application\Services\InvitationService;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * M10.4, Scenario A slice: submit a draft check-in protocol to the
 * counterparty (draft -> pending_counterparty), hard-gated on a real,
 * consumed entitlement.
 *
 * Backend (state machine, InvitationService, InvitationToken, and — the
 * actual gate — EntitlementService::consumeEntitlement, invoked from inside
 * InvitationService::inviteByEmail() for CHECK_IN protocols) is frozen and
 * already covered by HardGateTest / ProtocolStateMachineTest /
 * ParticipantTest / ScenarioBTest. This controller writes NO gate logic of
 * its own — it only wires the existing mechanism: create the initiator
 * Participant if missing (so inviteByEmail() can resolve $protocol->initiator()
 * and actually run the entitlement check), then call inviteByEmail(), then
 * transition the state machine. If inviteByEmail() throws
 * EntitlementRequiredException, nothing else in this request commits
 * (wrapped in a transaction) — that IS the hard-gate, not a new one.
 */
final class ProtocolSubmitController extends Controller
{
    use AuthorizesDraftProtocolMutation;

    public function store(Request $request, Protocol $protocol, InvitationService $invitationService): RedirectResponse
    {
        $this->assertMutable($protocol, 'Protokół można wysłać do drugiej strony tylko ze statusu „Szkic”.');

        $validated = $request->validate([
            'counterparty_email' => ['required', 'email', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $protocol->loadMissing('property');
        $initiatorRole = $protocol->property->declaration_type === DeclarationType::OWNER
            ? ParticipantRole::LANDLORD
            : ParticipantRole::TENANT;
        $counterpartyRole = $initiatorRole->opposite();

        try {
            DB::transaction(function () use ($protocol, $user, $invitationService, $initiatorRole, $counterpartyRole, $validated, $request): void {
                if (! $protocol->hasParticipant($user)) {
                    $invitationService->addInitiator($protocol, $user, $initiatorRole);
                }

                $token = $invitationService->inviteByEmail(
                    $protocol,
                    $validated['counterparty_email'],
                    $counterpartyRole,
                    false,
                    72,
                    $request->ip(),
                    $request->userAgent(),
                );

                $protocol->update([
                    'initiator_role' => $initiatorRole,
                    'counterparty_role' => $counterpartyRole,
                ]);
                $protocol->submitToCounterparty();

                // M10.4 dev convenience ONLY, non-production: real email
                // delivery of the magic-link is a later slice. The raw token
                // itself is still never persisted (G3 invariant) — only the
                // hash lives in invitation_tokens, same as always. This just
                // remembers the one-time URL so the initiator can still see
                // it after a page reload during dev/testing.
                if (! app()->environment('production')) {
                    $link = $invitationService->getInvitationUrl($token->raw_token);
                    $protocol->update([
                        'metadata' => array_merge($protocol->metadata ?? [], ['dev_magic_link' => $link]),
                    ]);
                }
            });
        } catch (EntitlementRequiredException $e) {
            throw ValidationException::withMessages([
                'counterparty_email' => 'Wymagana jest opłata przed wysłaniem protokołu do drugiej strony.',
            ]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'counterparty_email' => 'Nie udało się wysłać zaproszenia: '.$e->getMessage(),
            ]);
        }

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Protokół został wysłany do drugiej strony.');
    }
}
