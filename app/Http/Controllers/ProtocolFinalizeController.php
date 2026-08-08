<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Billing\Domain\Exceptions\EntitlementRequiredException;
use App\Modules\Document\Application\Services\PdfGenerationService;
use App\Modules\Identity\Infrastructure\Models\User;
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

        /** @var User $user */
        $user = Auth::user();
        $ipAddress = $request->ip();
        $userAgent = mb_substr((string) $request->userAgent(), 0, 255);

        try {
            DB::transaction(function () use ($protocol, $finalizeAction, $pdfService, $user, $ipAddress, $userAgent): void {
                $completed = $finalizeAction->execute($protocol, false, $ipAddress, $userAgent);

                $pdfService->getOrGenerateProtocolPdf($completed, $user, 'pl', $ipAddress, $userAgent);
            });
        } catch (ProtocolFinalizationException|EntitlementRequiredException $e) {
            throw ValidationException::withMessages(['finalize' => $e->getMessage()]);
        }

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Protokół został zakończony.');
    }
}
