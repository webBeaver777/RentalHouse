<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Acceptance\Application\Actions\SignProtocolAction;
use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Application\Services\InvitationService;
use App\Modules\Participation\Infrastructure\Models\InvitationToken;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * M10.5, Scenario A slice: guest (no account) accepts + signs a check-in
 * protocol via magic-link. Unauthenticated route — access control is the
 * token hash comparison already built into InvitationToken/InvitationService
 * (G3), not anything invented here.
 *
 * Backend (SignProtocolAction, InvitationService, InvitationToken, state
 * machine) is frozen and already covered by AsymmetricStateMachineTest /
 * ParticipantTest / AcceptanceTest / ScenarioBTest — this controller only
 * wires the existing mechanisms together, with one deliberate exception:
 * SignProtocolAction::validateCanSign() requires a non-null
 * Participant->user (App\Modules\Identity\Infrastructure\Models\User), but
 * a token-only guest has no account. To avoid touching the frozen action,
 * a lightweight "shadow" User is created (matching the invited email, a
 * random unusable password) purely so the participant<->user relation the
 * action expects exists — the guest never registers, logs in, or sees any
 * credential; the magic-link token stays their only means of access.
 */
final class GuestInvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitationToken = InvitationToken::byToken($token)->first();

        if (! $invitationToken) {
            return Inertia::render('Invitation/Invalid', ['reason' => 'not_found']);
        }

        /** @var Participant $participant */
        $participant = $invitationToken->participant;

        if ($participant->hasSigned()) {
            return $this->renderProtocol($token, $participant, alreadySigned: true);
        }

        if (! $invitationToken->isValid()) {
            $reason = match (true) {
                $invitationToken->isExpired() => 'expired',
                $invitationToken->isUsed() => 'used',
                default => 'revoked',
            };

            return Inertia::render('Invitation/Invalid', ['reason' => $reason]);
        }

        return $this->renderProtocol($token, $participant, alreadySigned: false);
    }

    public function sign(
        Request $request,
        string $token,
        InvitationService $invitationService,
        SignProtocolAction $signAction
    ): RedirectResponse {
        $invitationToken = InvitationToken::byToken($token)->valid()->first();
        abort_unless($invitationToken !== null, 404);

        $request->validate([
            'consent' => ['accepted'],
            'device_fingerprint' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Participant $participant */
        $participant = $invitationToken->participant;
        $protocol = $participant->protocol;
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
            DB::transaction(function () use ($token, $invitationService, $participant, $protocol, $signAction, $request, $userAgent, $deviceFingerprint): void {
                $guestUser = User::firstOrCreate(
                    ['email' => $participant->invited_email],
                    [
                        'name' => $participant->invited_email,
                        'password' => Str::random(40),
                    ]
                );

                $invitationService->acceptInvitation($token, $guestUser, $request->ip(), $userAgent);

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
            throw ValidationException::withMessages(['consent' => $e->getMessage()]);
        }

        return redirect()->route('invitation.accept', $token)
            ->with('status', 'Protokół został podpisany.');
    }

    public function evidence(string $token, Evidence $evidence): HttpResponse
    {
        $invitationToken = InvitationToken::byToken($token)->first();
        abort_unless($invitationToken !== null, 404);
        abort_unless($evidence->protocol_id === $invitationToken->participant->protocol_id, 404);

        $content = Storage::disk($evidence->disk)->get($evidence->path);
        abort_if($content === null, 404);

        return response($content, 200)
            ->header('Content-Type', $evidence->mime_type ?? 'application/octet-stream')
            ->header('Content-Disposition', 'inline; filename="'.addslashes($evidence->original_filename).'"')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    private function renderProtocol(string $token, Participant $participant, bool $alreadySigned): Response
    {
        $protocol = $participant->protocol;
        $protocol->loadMissing([
            'property',
            'createdBy',
            'rooms.catalogItem',
            'rooms.items.catalogItem',
            'rooms.items.condition',
            'rooms.items.photos',
        ]);

        return Inertia::render('Invitation/Accept', [
            'token' => $token,
            'valid' => true,
            'already_signed' => $alreadySigned,
            'participant' => [
                'role_label' => $participant->role->label(),
                'has_signed' => $participant->hasSigned(),
            ],
            'protocol' => [
                'id' => $protocol->id,
                'type_label' => $protocol->type->label(),
                'status' => $protocol->status->value,
                'status_label' => $protocol->status->label(),
                'title' => $protocol->title,
                'property' => [
                    'name' => $protocol->property->name,
                    'full_address' => $protocol->property->full_address,
                ],
                'initiator_name' => $protocol->createdBy->name,
                'rooms' => $protocol->rooms->map(fn (ProtocolRoom $room) => [
                    'id' => $room->id,
                    'display_name' => $room->display_name,
                    'items' => $room->items->map(fn (ProtocolItem $item) => [
                        'id' => $item->id,
                        'display_name' => $item->display_name,
                        'condition_name' => $item->condition_name,
                        'quantity' => $item->quantity,
                        'photos' => $item->photos->map(fn (Evidence $photo) => [
                            'id' => $photo->id,
                            'url' => route('invitation.evidence', [$token, $photo->id]),
                            'original_filename' => $photo->original_filename,
                        ])->values()->all(),
                    ])->values()->all(),
                ])->values()->all(),
            ],
        ]);
    }
}
