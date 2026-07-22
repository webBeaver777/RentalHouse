<?php

declare(strict_types=1);

namespace App\Modules\Participation\Application\Services;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\InvitationToken;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service for managing protocol invitations.
 *
 * Handles invitation creation, token generation, and acceptance flow.
 */
final class InvitationService
{
    private const DEFAULT_EXPIRATION_HOURS = 168; // 7 days

    /**
     * Invite a user by email to participate in a protocol.
     */
    public function inviteByEmail(
        Protocol $protocol,
        string $email,
        ParticipantRole $role,
        bool $isInitiator = false,
        int $expirationHours = self::DEFAULT_EXPIRATION_HOURS
    ): InvitationToken {
        $this->validateInvitation($protocol, $role);

        $participant = Participant::create([
            'protocol_id' => $protocol->id,
            'role' => $role,
            'is_initiator' => $isInitiator,
            'invited_email' => $email,
            'invited_at' => now(),
        ]);

        return $this->generateToken($participant, $email, $expirationHours);
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
     * Get invitation URL from token.
     */
    public function getInvitationUrl(InvitationToken $token): string
    {
        return url("/invitation/{$token->token}");
    }

    /**
     * Find valid token by string.
     */
    public function findValidToken(string $token): ?InvitationToken
    {
        return InvitationToken::byToken($token)->valid()->first();
    }

    /**
     * Generate secure token for participant.
     */
    private function generateToken(
        Participant $participant,
        string $email,
        int $expirationHours
    ): InvitationToken {
        return InvitationToken::create([
            'participant_id' => $participant->id,
            'token' => Str::random(64),
            'email' => $email,
            'expires_at' => now()->addHours($expirationHours),
        ]);
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
}
