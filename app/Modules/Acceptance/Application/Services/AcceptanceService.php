<?php

namespace App\Modules\Acceptance\Application\Services;

use App\Modules\Acceptance\Domain\Enums\AcceptanceStatus;
use App\Modules\Acceptance\Infrastructure\Models\ItemAcceptance;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use Illuminate\Support\Collection;

class AcceptanceService
{
    /**
     * Accept an item.
     */
    public function acceptItem(ProtocolItem $item, Participant $participant): ItemAcceptance
    {
        $acceptance = $this->getOrCreateAcceptance($item, $participant);
        $acceptance->accept();

        return $acceptance;
    }

    /**
     * Dispute an item.
     */
    public function disputeItem(ProtocolItem $item, Participant $participant, string $reason): ItemAcceptance
    {
        $acceptance = $this->getOrCreateAcceptance($item, $participant);
        $acceptance->dispute($reason);

        return $acceptance;
    }

    /**
     * Resolve a dispute.
     */
    public function resolveDispute(ItemAcceptance $acceptance, string $notes): ItemAcceptance
    {
        $acceptance->resolveDispute($notes);

        return $acceptance;
    }

    /**
     * Accept all items in protocol for participant.
     */
    public function acceptAllItems(Protocol $protocol, Participant $participant): Collection
    {
        $items = $this->getProtocolItems($protocol);
        $acceptances = collect();

        foreach ($items as $item) {
            $acceptances->push($this->acceptItem($item, $participant));
        }

        return $acceptances;
    }

    /**
     * Get acceptance summary for protocol.
     */
    public function getProtocolSummary(Protocol $protocol): array
    {
        $items = $this->getProtocolItems($protocol);
        $totalItems = $items->count();

        $acceptances = ItemAcceptance::byProtocol($protocol)->get();

        return [
            'total_items' => $totalItems,
            'pending' => $acceptances->where('status', AcceptanceStatus::PENDING)->count(),
            'accepted' => $acceptances->where('status', AcceptanceStatus::ACCEPTED)->count(),
            'disputed' => $acceptances->where('status', AcceptanceStatus::DISPUTED)->count(),
            'unresolved_disputes' => $acceptances
                ->where('status', AcceptanceStatus::DISPUTED)
                ->whereNull('resolved_at')
                ->count(),
            'fully_accepted_items' => $this->countFullyAcceptedItems($items),
        ];
    }

    /**
     * Get acceptance summary for participant.
     */
    public function getParticipantSummary(Protocol $protocol, Participant $participant): array
    {
        $items = $this->getProtocolItems($protocol);
        $totalItems = $items->count();

        $acceptances = ItemAcceptance::byProtocol($protocol)
            ->byParticipant($participant)
            ->get();

        $reviewed = $acceptances->count();

        return [
            'total_items' => $totalItems,
            'reviewed' => $reviewed,
            'pending' => $totalItems - $reviewed,
            'accepted' => $acceptances->where('status', AcceptanceStatus::ACCEPTED)->count(),
            'disputed' => $acceptances->where('status', AcceptanceStatus::DISPUTED)->count(),
            'progress_percent' => $totalItems > 0 ? round(($reviewed / $totalItems) * 100) : 0,
        ];
    }

    /**
     * Check if all items are fully accepted by all participants.
     */
    public function isProtocolFullyAccepted(Protocol $protocol): bool
    {
        $items = $this->getProtocolItems($protocol);
        $participants = $protocol->participants;

        if ($items->isEmpty() || $participants->isEmpty()) {
            return false;
        }

        foreach ($items as $item) {
            foreach ($participants as $participant) {
                if (!$item->isAcceptedBy($participant)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if protocol has any unresolved disputes.
     */
    public function hasUnresolvedDisputes(Protocol $protocol): bool
    {
        return ItemAcceptance::byProtocol($protocol)
            ->unresolvedDisputes()
            ->exists();
    }

    /**
     * Get all disputed items in protocol.
     */
    public function getDisputedItems(Protocol $protocol): Collection
    {
        $disputedItemIds = ItemAcceptance::byProtocol($protocol)
            ->disputed()
            ->pluck('protocol_item_id')
            ->unique();

        return ProtocolItem::whereIn('id', $disputedItemIds)->get();
    }

    /**
     * Get or create acceptance record.
     */
    private function getOrCreateAcceptance(ProtocolItem $item, Participant $participant): ItemAcceptance
    {
        return ItemAcceptance::firstOrCreate(
            [
                'protocol_item_id' => $item->id,
                'participant_id' => $participant->id,
            ],
            [
                'protocol_id' => $item->room->protocol_id,
                'status' => AcceptanceStatus::PENDING,
            ]
        );
    }

    /**
     * Get all items in protocol.
     */
    private function getProtocolItems(Protocol $protocol): Collection
    {
        return ProtocolItem::whereHas('room', function ($query) use ($protocol) {
            $query->where('protocol_id', $protocol->id);
        })->get();
    }

    /**
     * Count items that are fully accepted by all participants.
     */
    private function countFullyAcceptedItems(Collection $items): int
    {
        return $items->filter(fn($item) => $item->isFullyAccepted())->count();
    }
}
