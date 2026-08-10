<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Billing\Domain\Exceptions\EntitlementRequiredException;
use App\Modules\Document\Application\Services\PdfGenerationService;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Protocol\Application\Actions\FinalizeCheckInAction;
use App\Modules\Protocol\Domain\Exceptions\ProtocolFinalizationException;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * M10.6, Scenario A slice: finalize a signed check-in protocol — seals it
 * (completed, immutable) and generates the frozen PDF. Wires the existing
 * frozen FinalizeCheckInAction (hard-gate + state transition) and
 * PdfGenerationService::getOrGenerateProtocolPdf (freezes document_hash on
 * first generation, returns the existing document on repeat calls) — no
 * new gate/state logic here.
 *
 * M11, Scenario B: also wires the unilateral fallback that
 * FinalizeCheckInAction already supports (allowUnilateral=true, tested by
 * ScenarioBTest::can_finalize_unilateral_when_allowed) — a request flag,
 * not a new gate. Guard 2: which outcome actually happens is still decided
 * entirely by the frozen action's own validate() (counterparty signed or
 * not) — this controller never forces bilateral to look unilateral or vice
 * versa.
 *
 * legal_mode (M13 step 0): determined inside FinalizeCheckInAction itself
 * now, from the actual fact of signatures/role — not set here. This
 * controller only decides allowUnilateral (the request flag, Guard-2'd
 * against a counterparty that already signed) and passes it through;
 * PdfTemplateType::fromProtocol() and QrVerificationController read the
 * domain-set value.
 *
 * Entitlement double-consume note: FinalizeCheckInAction's own hard-gate
 * calls EntitlementService::consumeEntitlement() for the SAME
 * (protocol, CREATE_CHECK_IN) pair already consumed at submit time
 * (M10.4 / InvitationService::inviteByEmail). consumeEntitlement() is
 * idempotent per (protocol_id, action) — it looks up an existing
 * EntitlementUsage row first and returns it unchanged instead of
 * decrementing quantity_used again. This was verified by reading
 * EntitlementService before wiring (not assumed) and is covered by
 * ProtocolFinalizeHttpTest::test_finalize_does_not_double_consume_entitlement.
 */
final class ProtocolFinalizeController extends Controller
{
    public function store(
        Request $request,
        Protocol $protocol,
        FinalizeCheckInAction $finalizeAction,
        PdfGenerationService $pdfService
    ): RedirectResponse {
        abort_unless($protocol->created_by_user_id === Auth::id(), 403);

        $requestedUnilateral = $request->boolean('unilateral');

        // Guard 2 (server-side, not just UI): if the counterparty has
        // already signed, this IS a bilateral protocol no matter what the
        // request says — never let a stray/misused `unilateral=true` label
        // a genuinely two-signature protocol as unilateral. This is the
        // "don't cut corners while bilateral is still possible" rule from
        // CLAUDE.md, enforced here rather than trusted from the client.
        $counterpartySigned = $protocol->counterparty()?->hasSigned() ?? false;
        $allowUnilateral = $requestedUnilateral && ! $counterpartySigned;

        // Guard 2 (server-side, not just UI): unilateral finalize only
        // renders correctly for a tenant-initiated protocol —
        // PdfTemplateType::fromProtocol() has no distinct unilateral
        // template for a landlord initiator, so it would silently fall
        // back to BILATERAL_CHECKIN and produce an unlabelled unilateral
        // document indistinguishable from a real bilateral one. Reject
        // rather than produce that artifact.
        if ($allowUnilateral) {
            abort_unless($protocol->initiator_role === ParticipantRole::TENANT, 422, 'Zakończenie jednostronne jest dostępne tylko dla protokołów inicjowanych przez najemcę.');
        }

        /** @var User $user */
        $user = Auth::user();
        $ipAddress = $request->ip();
        $userAgent = mb_substr((string) $request->userAgent(), 0, 255);

        try {
            DB::transaction(function () use ($protocol, $finalizeAction, $pdfService, $user, $ipAddress, $userAgent, $allowUnilateral): void {
                $completed = $finalizeAction->execute($protocol, $allowUnilateral, $ipAddress, $userAgent);

                $pdfService->getOrGenerateProtocolPdf($completed, $user, 'pl', $ipAddress, $userAgent);
            });
        } catch (ProtocolFinalizationException|EntitlementRequiredException $e) {
            throw ValidationException::withMessages(['finalize' => $e->getMessage()]);
        }

        return redirect()->route('protocols.show', $protocol)
            ->with('status', $allowUnilateral
                ? 'Protokół został zakończony jednostronnie.'
                : 'Protokół został zakończony.');
    }
}
