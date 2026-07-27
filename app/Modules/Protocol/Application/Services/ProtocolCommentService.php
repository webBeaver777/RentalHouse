<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Application\Services;

use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolComment;
use Illuminate\Support\Collection;

/**
 * D6: Service for managing protocol comments.
 *
 * Allows counterparty and initiator to comment on protocol elements.
 */
final class ProtocolCommentService
{
    /**
     * Add a comment to the protocol.
     */
    public function addComment(
        Protocol $protocol,
        Participant $author,
        string $body,
        ?string $commentableType = null,
        ?string $commentableId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceFingerprint = null
    ): ProtocolComment {
        $comment = ProtocolComment::create([
            'protocol_id' => $protocol->id,
            'author_role' => $author->role,
            'author_user_id' => $author->user_id,
            'body' => $body,
            'commentable_type' => $commentableType,
            'commentable_id' => $commentableId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_fingerprint' => $deviceFingerprint,
        ]);

        // D7: Record comment event
        InspectionEvent::record(
            $protocol,
            InspectionEventType::COMMENT_ADDED,
            $author->role->value,
            $author->user_id,
            [
                'comment_id' => $comment->id,
                'commentable_type' => $commentableType,
                'commentable_id' => $commentableId,
            ],
            $ipAddress,
            $userAgent
        );

        return $comment;
    }

    /**
     * Get all comments for a protocol.
     */
    public function getCommentsForProtocol(Protocol $protocol): Collection
    {
        return ProtocolComment::where('protocol_id', $protocol->id)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get comments for a specific element.
     */
    public function getCommentsForElement(
        Protocol $protocol,
        string $type,
        string $id
    ): Collection {
        return ProtocolComment::where('protocol_id', $protocol->id)
            ->forEntity($type, $id)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get comments by role.
     */
    public function getCommentsByRole(Protocol $protocol, ParticipantRole $role): Collection
    {
        return ProtocolComment::where('protocol_id', $protocol->id)
            ->byRole($role)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Check if protocol has any comments.
     */
    public function hasComments(Protocol $protocol): bool
    {
        return ProtocolComment::where('protocol_id', $protocol->id)->exists();
    }

    /**
     * Count comments for protocol.
     */
    public function countComments(Protocol $protocol): int
    {
        return ProtocolComment::where('protocol_id', $protocol->id)->count();
    }
}
