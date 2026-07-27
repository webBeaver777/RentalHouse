<?php

declare(strict_types=1);

namespace App\Modules\Acceptance\Application\Actions;

use App\Modules\Acceptance\Application\Services\AcceptanceService;
use App\Modules\Acceptance\Domain\Enums\AcceptanceType;
use App\Modules\Acceptance\Infrastructure\Models\Acceptance;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use InvalidArgumentException;

/**
 * Action for signing a protocol.
 *
 * Implements dual-track signing logic:
 * - Check-in: requires 2 signatures (bilateral)
 * - Check-out: requires 1 signature (initiator) + objection window
 */
final class SignProtocolAction
{
    public function __construct(
        private readonly AcceptanceService $acceptanceService,
    ) {}

    /**
     * Sign protocol on behalf of participant.
     */
    public function execute(
        Protocol $protocol,
        Participant $participant,
        ?string $signatureData = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceFingerprint = null
    ): Protocol {
        $this->validateCanSign($protocol, $participant);

        // Sign for participant
        $participant->sign($signatureData);

        // G2: Record acceptance with forensic data
        $this->recordAcceptance(
            $protocol,
            $participant,
            $ipAddress,
            $userAgent,
            $deviceFingerprint
        );

        // D7: Record signature event
        InspectionEvent::record(
            $protocol,
            InspectionEventType::SIGNATURE_ADDED,
            $participant->role->value,
            $participant->user_id,
            ['is_initiator' => $participant->is_initiator],
            $ipAddress,
            $userAgent
        );

        // Check if protocol should transition based on type
        $this->handlePostSignTransition($protocol);

        return $protocol->fresh();
    }

    /**
     * G2: Record acceptance in acceptances table.
     *
     * Creates forensic record with consent_text_snapshot, ip, user_agent, device_fingerprint.
     */
    private function recordAcceptance(
        Protocol $protocol,
        Participant $participant,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $deviceFingerprint
    ): void {
        // Determine acceptance type based on protocol state
        $hasComments = $this->acceptanceService->hasUnresolvedDisputes($protocol);
        $acceptanceType = $hasComments
            ? AcceptanceType::ACCEPTED_WITH_COMMENTS
            : AcceptanceType::ACCEPTED;

        // Generate consent text snapshot
        $consentText = $this->generateConsentText($protocol, $participant);

        Acceptance::recordAcceptance(
            $protocol,
            $participant->role,
            $acceptanceType,
            $participant->user_id,
            $participant->isCounterparty() ? $participant->id : null,
            $consentText,
            $ipAddress,
            $userAgent,
            $deviceFingerprint
        );
    }

    /**
     * Generate consent text snapshot for acceptance.
     */
    private function generateConsentText(Protocol $protocol, Participant $participant): string
    {
        $typeLabel = $protocol->type === ProtocolType::CHECK_IN
            ? 'protokołu zdawczo-odbiorczego (przekazanie)'
            : 'protokołu zdawczo-odbiorczego (zwrot)';

        $roleLabel = $participant->role->label();
        $propertyAddress = $protocol->property?->full_address ?? 'nieznany adres';

        return sprintf(
            'Oświadczam, że zapoznałem/am się z treścią %s dla nieruchomości pod adresem %s i akceptuję jego treść jako %s.',
            $typeLabel,
            $propertyAddress,
            $roleLabel
        );
    }

    /**
     * Validate if participant can sign.
     */
    private function validateCanSign(Protocol $protocol, Participant $participant): void
    {
        if (! $protocol->hasParticipant($participant->user)) {
            throw new InvalidArgumentException('User is not a participant in this protocol');
        }

        if ($participant->hasSigned()) {
            throw new InvalidArgumentException('Participant has already signed this protocol');
        }

        if (! $protocol->canTransitionTo(ProtocolStatus::PENDING_SIGNATURES) &&
            $protocol->status !== ProtocolStatus::PENDING_SIGNATURES) {
            throw new InvalidArgumentException(
                'Protocol must be in pending_signatures status or able to transition to it'
            );
        }

        // For check-in, ensure all items are accepted before signing
        if ($protocol->type === ProtocolType::CHECK_IN) {
            if ($this->acceptanceService->hasUnresolvedDisputes($protocol)) {
                throw new InvalidArgumentException(
                    'Cannot sign protocol with unresolved disputes'
                );
            }
        }
    }

    /**
     * Handle state transition after signing.
     */
    private function handlePostSignTransition(Protocol $protocol): void
    {
        // Reload participants
        $protocol->load('participants');

        if ($protocol->type === ProtocolType::CHECK_IN) {
            $this->handleCheckInSignature($protocol);
        } else {
            $this->handleCheckOutSignature($protocol);
        }
    }

    /**
     * Handle check-in signing (bilateral: requires 2 signatures).
     */
    private function handleCheckInSignature(Protocol $protocol): void
    {
        // Check if all participants have signed
        if ($protocol->allParticipantsSigned()) {
            $protocol->markAsSigned();
        }
    }

    /**
     * Handle check-out signing (unilateral: 1 signature + objection window).
     */
    private function handleCheckOutSignature(Protocol $protocol): void
    {
        $initiator = $protocol->initiator();

        // Check-out only requires initiator signature
        if ($initiator && $initiator->hasSigned()) {
            $protocol->markAsSigned();
        }
    }

    /**
     * Check if protocol can be signed by participant.
     */
    public function canSign(Protocol $protocol, Participant $participant): bool
    {
        try {
            $this->validateCanSign($protocol, $participant);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Get signing requirements for protocol.
     */
    public function getSigningRequirements(Protocol $protocol): array
    {
        $participants = $protocol->participants;
        $signed = $participants->filter(fn ($p) => $p->hasSigned());
        $unsigned = $participants->filter(fn ($p) => ! $p->hasSigned());

        if ($protocol->type === ProtocolType::CHECK_IN) {
            return [
                'type' => 'bilateral',
                'required_signatures' => 2,
                'current_signatures' => $signed->count(),
                'signed_by' => $signed->map(fn ($p) => [
                    'id' => $p->id,
                    'role' => $p->role->value,
                    'name' => $p->display_name,
                    'signed_at' => $p->signed_at?->toIso8601String(),
                ])->values()->toArray(),
                'awaiting_signature' => $unsigned->map(fn ($p) => [
                    'id' => $p->id,
                    'role' => $p->role->value,
                    'name' => $p->display_name,
                ])->values()->toArray(),
                'can_complete' => $signed->count() >= 2,
            ];
        }

        // Check-out: unilateral
        $initiator = $protocol->initiator();

        return [
            'type' => 'unilateral',
            'required_signatures' => 1,
            'current_signatures' => $initiator?->hasSigned() ? 1 : 0,
            'initiator_signed' => $initiator?->hasSigned() ?? false,
            'objection_window_hours' => 72,
            'signed_by' => $signed->map(fn ($p) => [
                'id' => $p->id,
                'role' => $p->role->value,
                'name' => $p->display_name,
                'signed_at' => $p->signed_at?->toIso8601String(),
            ])->values()->toArray(),
            'can_complete' => $initiator?->hasSigned() ?? false,
        ];
    }
}
