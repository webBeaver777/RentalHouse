<?php

declare(strict_types=1);

namespace App\Modules\Participation\Application\Services;

use App\Modules\Billing\Application\Services\EntitlementService;
use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Exceptions\EntitlementRequiredException;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\InvitationToken;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use InvalidArgumentException;

/**
 * G3: Service for managing protocol invitations.
 *
 * D3 HARD-GATE: For check-in, requires consumed entitlement to send magic-link.
 * G3: Stores token_hash, not raw token. Uses configurable expiry from lifecycle config.
 *
 * Handles invitation creation, token generation, and acceptance flow.
 */
final class InvitationService
{
    // G3: Use config for expiration, fallback to 72 hours
    private const DEFAULT_EXPIRATION_HOURS = 72;

    public function __construct(
        private readonly EntitlementService $entitlementService
    ) {}

    /**
     * Invite a user by email to participate in a protocol.
     *
     * D3: For check-in protocols, requires entitlement to send magic-link.
     *
     * @throws EntitlementRequiredException
     */
    public function inviteByEmail(
        Protocol $protocol,
        string $email,
        ParticipantRole $role,
        bool $isInitiator = false,
        int $expirationHours = self::DEFAULT_EXPIRATION_HOURS,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): InvitationToken {
        $this->validateInvitation($protocol, $role);

        // D3: HARD-GATE for check-in - require entitlement before sending magic-link
        if (! $isInitiator && $protocol->type === ProtocolType::CHECK_IN) {
            $initiator = $protocol->initiator();
            if ($initiator?->user) {
                $this->entitlementService->consumeEntitlement(
                    $initiator->user,
                    $protocol,
                    AllowedAction::CREATE_CHECK_IN,
                    $ipAddress,
                    $userAgent
                );
            }
        }

        $participant = Participant::create([
            'protocol_id' => $protocol->id,
            'role' => $role,
            'is_initiator' => $isInitiator,
            'invited_email' => $email,
            'invited_at' => now(),
        ]);

        $token = $this->generateToken($participant, $email, $expirationHours);

        // D7: Record timeline event
        if (! $isInitiator) {
            InspectionEvent::record(
                $protocol,
                InspectionEventType::INVITATION_SENT,
                $protocol->initiator()?->role?->value,
                $protocol->initiator()?->user_id,
                ['email' => $this->maskEmail($email), 'role' => $role->value],
                $ipAddress,
                $userAgent
            );
        }

        return $token;
    }

    /**
     * Invite an existing user to participate in a protocol.
     */
    public function inviteUser(
        Protocol $protocol,
        User $user,
        ParticipantRole $role,
        bool $isInitiator = false,
        int $expirationHours = self::DEFAULT_EXPIRATION_HOURS
    ): InvitationToken {
        $this->validateInvitation($protocol, $role);

        $participant = Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_initiator' => $isInitiator,
            'invited_email' => $user->email,
            'invited_at' => now(),
        ]);

        return $this->generateToken($participant, $user->email, $expirationHours);
    }

    /**
     * Add initiator directly (no invitation needed).
     */
    public function addInitiator(
        Protocol $protocol,
        User $user,
        ParticipantRole $role
    ): Participant {
        $this->validateInvitation($protocol, $role);

        return Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_initiator' => true,
            'invited_email' => $user->email,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);
    }

    /**
     * Accept invitation using token.
     */
    public function acceptInvitation(
        string $token,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): Participant {
        $invitationToken = InvitationToken::byToken($token)->valid()->first();

        if (! $invitationToken) {
            throw new InvalidArgumentException('Invalid or expired invitation token');
        }

        $participant = $invitationToken->participant;

        // If user is provided, link them to participant
        if ($user) {
            $participant->user_id = $user->id;
        }

        $participant->accept();
        $invitationToken->markAsUsed($ip, $userAgent);

        // D7: Record timeline events
        $protocol = $participant->protocol;

        InspectionEvent::record(
            $protocol,
            InspectionEventType::MAGIC_LINK_USED,
            $participant->role->value,
            $participant->user_id,
            null,
            $ip,
            $userAgent
        );

        InspectionEvent::record(
            $protocol,
            InspectionEventType::SIGNATURE_ADDED,
            $participant->role->value,
            $participant->user_id,
            ['acceptance_type' => 'accepted'],
            $ip,
            $userAgent
        );

        return $participant;
    }

    /**
     * Decline invitation using token.
     */
    public function declineInvitation(
        string $token,
        ?string $ip = null,
        ?string $userAgent = null
    ): Participant {
        $invitationToken = InvitationToken::byToken($token)->valid()->first();

        if (! $invitationToken) {
            throw new InvalidArgumentException('Invalid or expired invitation token');
        }

        $participant = $invitationToken->participant;
        $participant->decline();
        $invitationToken->markAsUsed($ip, $userAgent);

        // D7: Record timeline events
        $protocol = $participant->protocol;

        InspectionEvent::record(
            $protocol,
            InspectionEventType::MAGIC_LINK_USED,
            $participant->role->value,
            $participant->user_id,
            null,
            $ip,
            $userAgent
        );

        InspectionEvent::record(
            $protocol,
            InspectionEventType::SIGNATURE_DECLINED,
            $participant->role->value,
            $participant->user_id,
            null,
            $ip,
            $userAgent
        );

        return $participant;
    }

    /**
     * Resend invitation with new token.
     */
    public function resendInvitation(
        Participant $participant,
        int $expirationHours = self::DEFAULT_EXPIRATION_HOURS
    ): InvitationToken {
        if ($participant->isAccepted()) {
            throw new InvalidArgumentException('Cannot resend invitation for accepted participant');
        }

        // Revoke existing tokens
        InvitationToken::where('participant_id', $participant->id)
            ->valid()
            ->update(['revoked_at' => now()]);

        // Update invited_at
        $participant->update(['invited_at' => now()]);

        return $this->generateToken(
            $participant,
            $participant->invited_email,
            $expirationHours
        );
    }

    /**
     * Revoke all tokens for a participant.
     */
    public function revokeInvitation(Participant $participant): void
    {
        InvitationToken::where('participant_id', $participant->id)
            ->valid()
            ->update(['revoked_at' => now()]);
    }

    /**
     * G3: Get invitation URL from raw token.
     *
     * Note: This should be called immediately after generateToken,
     * as raw_token is not stored and cannot be retrieved later.
     */
    public function getInvitationUrl(string $rawToken): string
    {
        return url("/invitation/{$rawToken}");
    }

    /**
     * G3: Find valid token by raw token string.
     */
    public function findValidToken(string $rawToken): ?InvitationToken
    {
        return InvitationToken::findByRawToken($rawToken);
    }

    /**
     * G3: Generate secure token for participant.
     *
     * Returns array with 'token' (model) and 'raw_token' (to send to user).
     * Raw token is NEVER stored - only hash.
     */
    private function generateToken(
        Participant $participant,
        string $email,
        int $expirationHours
    ): InvitationToken {
        $expiryHours = (int) config('lifecycle.invitation_token_hours', $expirationHours);
        $rawToken = bin2hex(random_bytes(32)); // 64 character hex string

        $token = InvitationToken::create([
            'participant_id' => $participant->id,
            'token_hash' => hash('sha256', $rawToken),
            'email' => $email,
            'expires_at' => now()->addHours($expiryHours),
        ]);

        // Store raw_token temporarily for URL generation
        // Note: The caller must use getInvitationUrl immediately
        $token->setAttribute('raw_token', $rawToken);

        return $token;
    }

    /**
     * Validate invitation can be created.
     */
    private function validateInvitation(Protocol $protocol, ParticipantRole $role): void
    {
        // Check if role is already taken
        $existingParticipant = $protocol->participants()
            ->where('role', $role)
            ->whereNull('declined_at')
            ->first();

        if ($existingParticipant) {
            throw new InvalidArgumentException(
                "Role {$role->value} is already assigned in this protocol"
            );
        }

        // Check if protocol is in valid state for invitations
        if ($protocol->isFinal()) {
            throw new InvalidArgumentException(
                'Cannot invite participants to a finalized protocol'
            );
        }
    }

    /**
     * Mask email for logging (RODO compliance).
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        if (strlen($local) <= 2) {
            return $local[0].'***@'.$domain;
        }

        return $local[0].'***'.substr($local, -1).'@'.$domain;
    }
}
