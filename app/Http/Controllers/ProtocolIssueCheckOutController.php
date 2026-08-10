<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Acceptance\Application\Actions\SignProtocolAction;
use App\Modules\Billing\Domain\Exceptions\EntitlementRequiredException;
use App\Modules\Document\Application\Services\PdfGenerationService;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Application\Services\InvitationService;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Application\Actions\IssueCheckOutAction;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Domain\Exceptions\ProtocolFinalizationException;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * M13 step 3, Scenario C slice 2: "Wydaj akt" — issue the one-sided
 * check-out act. Wires the frozen IssueCheckOutAction (act_issued_at,
 * objection window, legal_mode unilateral — M13 step 0, hard-gate) behind a
 * single button click, exactly the asymmetry the action itself documents:
 * ONE acceptance (the initiator's) issues the act — the counterparty is
 * notified, never blocked on.
 *
 * Unlike check-in (submit -> sign -> finalize as three separate,
 * counterparty-facing steps), a check-out draft has no participants at all
 * yet (see ProtocolCheckOutController — C1 stops at draft, no
 * participant/signing wiring). So this controller does, atomically, what
 * ScenarioCTest's "full flow" test does step by step directly against the
 * domain: add the initiator participant if missing, invite the
 * counterparty as an INFORMATIONAL magic-link (not gating anything — they
 * never need to sign, only get the objection window), transition
 * draft -> pending_counterparty -> pending_signatures, sign as initiator
 * (frozen SignProtocolAction, same one the guest/initiator check-in flows
 * use), then issue the act. All in one DB transaction: if the hard-gate
 * (missing entitlement) rejects it, everything rolls back to draft.
 *
 * Ordering note: the counterparty invite MUST happen BEFORE
 * IssueCheckOutAction runs. InvitationService::validateInvitation() rejects
 * inviting into a "final" protocol, and issuance sets status=COMPLETED —
 * inviting after would always throw. This mirrors the exact order
 * ScenarioCTest's frozen full-flow test already exercises.
 */
final class ProtocolIssueCheckOutController extends Controller
{
    public function store(
        Request $request,
        Protocol $protocol,
        InvitationService $invitationService,
        SignProtocolAction $signAction,
        IssueCheckOutAction $issueAction,
        PdfGenerationService $pdfService
    ): RedirectResponse {
        abort_unless($protocol->created_by_user_id === Auth::id(), 403);
        abort_unless($protocol->type === ProtocolType::CHECK_OUT, 404);
        abort_unless($protocol->status !== ProtocolStatus::COMPLETED, 422, 'Akt wyjazdu został już wydany.');

        /** @var User $user */
        $user = Auth::user();
        $ipAddress = $request->ip();
        $userAgent = mb_substr((string) $request->userAgent(), 0, 255);

        try {
            DB::transaction(function () use ($protocol, $user, $invitationService, $signAction, $issueAction, $pdfService, $ipAddress, $userAgent): void {
                if (! $protocol->hasParticipant($user)) {
                    $invitationService->addInitiator($protocol, $user, $protocol->initiator_role);
                }

                $this->notifyCounterparty($protocol, $invitationService, $ipAddress, $userAgent);

                if ($protocol->status === ProtocolStatus::DRAFT) {
                    $protocol->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
                    $protocol->transitionTo(ProtocolStatus::PENDING_SIGNATURES);
                } elseif ($protocol->status === ProtocolStatus::PENDING_COUNTERPARTY) {
                    $protocol->transitionTo(ProtocolStatus::PENDING_SIGNATURES);
                }

                /** @var Participant $participant */
                $participant = $protocol->participants()->where('user_id', $user->id)->firstOrFail();
                if (! $participant->hasSigned()) {
                    $signAction->execute($protocol->refresh(), $participant->refresh(), null, $ipAddress, $userAgent, null);
                }

                $issued = $issueAction->execute($protocol->refresh(), null, $ipAddress, $userAgent);

                $pdfService->getOrGenerateProtocolPdf($issued, $user, 'pl', $ipAddress, $userAgent);
            });
        } catch (ProtocolFinalizationException|EntitlementRequiredException $e) {
            throw ValidationException::withMessages(['issue' => $e->getMessage()]);
        }

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Akt wyjazdu został wydany jednostronnie.');
    }

    /**
     * Informational magic-link for the counterparty (objection window,
     * guest reception is C3 — see IssueCheckOutAction). Their email is
     * known from the baseline check-in's own participant on the same role
     * — no new "who is the tenant" input invented. No-op if there is no
     * baseline, that email can't be resolved, or the role is already
     * assigned (retry after a rolled-back attempt).
     */
    private function notifyCounterparty(
        Protocol $protocol,
        InvitationService $invitationService,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        if ($protocol->linked_checkin_id === null) {
            return;
        }

        if ($protocol->participants()->where('role', $protocol->counterparty_role)->exists()) {
            return;
        }

        $protocol->loadMissing('linkedCheckin.participants.user');
        $baselineCheckIn = $protocol->linkedCheckin;

        if ($baselineCheckIn === null) {
            return;
        }

        $counterpartyParticipant = $baselineCheckIn->participants->firstWhere('role', $protocol->counterparty_role);
        $counterpartyEmail = $counterpartyParticipant?->user?->email ?? $counterpartyParticipant?->invited_email;

        if ($counterpartyEmail === null) {
            return;
        }

        $token = $invitationService->inviteByEmail(
            $protocol,
            $counterpartyEmail,
            $protocol->counterparty_role,
            false,
            72,
            $ipAddress,
            $userAgent,
        );

        if (! app()->environment('production')) {
            $link = $invitationService->getInvitationUrl($token->raw_token);
            $protocol->update([
                'metadata' => array_merge($protocol->metadata ?? [], ['dev_magic_link' => $link]),
            ]);
        }
    }
}
