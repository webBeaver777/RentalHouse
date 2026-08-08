<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Billing\Application\Services\EntitlementService;
use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Document\Domain\Enums\DocumentType;
use App\Modules\Document\Infrastructure\Models\GeneratedDocument;
use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Application\Actions\FinalizeCheckInAction;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * M10.1, Scenario A slice: check-in protocol creation entry point from
 * an existing property. M10.2 extends show() with rooms/items + catalog
 * pickers (mutations live in ProtocolRoomController/ProtocolItemController).
 * M10.3 extends it further with per-item photo evidence (mutations live in
 * ProtocolItemPhotoController, serving in ProtocolEvidenceController).
 * M10.4 extends it with entitlement/dev-magic-link status for the payment
 * + submit-to-counterparty step (mutation lives in ProtocolSubmitController;
 * dev-grant in BillingController). M10.5 extends it with the initiator's
 * own sign status (mutation lives in ProtocolSignController; guest side in
 * GuestInvitationController). M10.6 extends it with finalize/PDF/QR status
 * (mutation lives in ProtocolFinalizeController; PDF served by
 * ProtocolDocumentController; QR verification is the existing public
 * QrVerificationController, unchanged).
 *
 * Backend (Protocol model, ProtocolType, state machine, ProtocolRoom,
 * ProtocolItem, catalog, Evidence, EntitlementService, SignProtocolAction,
 * FinalizeCheckInAction, PdfGenerationService) is frozen and already
 * covered by ProtocolTest / ProtocolStateMachineTest /
 * AsymmetricStateMachineTest / ProtocolRoomItemTest / EvidenceTest /
 * EvidenceUploadServiceTest / HardGateTest / ParticipantTest /
 * AcceptanceTest / DocumentHashFreezeTest / QrVerificationTest — this
 * controller only wires UI + routes, the same shape as
 * PropertyController::store.
 *
 * M11 extends it with the tenant-initiated role-flip: initiator/counterparty
 * role is already computed from the property's declaration_type by
 * ProtocolSubmitController (unchanged since M10.4) — this controller only
 * mirrors that same computation to label the draft-stage "send" field
 * correctly, and splits the single finalize flag into bilateral/unilateral
 * so the right one shows (see ProtocolFinalizeController for the
 * legal_mode wiring that makes the PDF/QR page reflect which one ran).
 *
 * NOT in this slice: real email delivery, Scenario C/D, B2B (M11+).
 */
final class ProtocolController extends Controller
{
    public function create(Request $request): Response
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer'],
        ]);

        $property = Property::where('user_id', Auth::id())
            ->findOrFail($validated['property_id']);

        return Inertia::render('Protocol/Create', [
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'full_address' => $property->full_address,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $property = Property::where('user_id', Auth::id())
            ->findOrFail($validated['property_id']);

        $title = $validated['title'] ?? null;

        $protocol = Protocol::create([
            'property_id' => $property->id,
            'created_by_user_id' => Auth::id(),
            'type' => ProtocolType::CHECK_IN,
            'title' => $title !== null && $title !== ''
                ? $title
                : 'Protokół wjazdu — '.$property->name,
        ]);

        return redirect()->route('protocols.show', $protocol);
    }

    public function show(
        Protocol $protocol,
        EntitlementService $entitlementService,
        FinalizeCheckInAction $finalizeAction
    ): Response {
        abort_unless($protocol->created_by_user_id === Auth::id(), 403);

        $protocol->loadMissing([
            'property',
            'createdBy',
            'rooms.catalogItem',
            'rooms.items.catalogItem',
            'rooms.items.condition',
            'rooms.items.photos',
            'participants',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $requiredAction = EntitlementService::getRequiredAction($protocol);
        $isDraft = $protocol->status === ProtocolStatus::DRAFT;
        $isCompleted = $protocol->status === ProtocolStatus::COMPLETED;

        $initiatorParticipant = $protocol->participants->firstWhere('user_id', $user->id);
        $counterpartyParticipant = $protocol->participants->firstWhere('is_initiator', false);
        $canSign = in_array($protocol->status, [ProtocolStatus::PENDING_COUNTERPARTY, ProtocolStatus::PENDING_SIGNATURES], true)
            && $initiatorParticipant !== null
            && ! $initiatorParticipant->hasSigned();

        // M11: exactly one finalize action should be offered at a time —
        // bilateral once the counterparty has actually signed, unilateral
        // (Scenario B fallback) only while that hasn't happened yet. Both
        // flags come straight from the frozen FinalizeCheckInAction::canFinalize()
        // (allowUnilateral true/false) — no gate logic invented here.
        //
        // Guard 2: unilateral is only offered when the initiator is the
        // TENANT. PdfTemplateType::fromProtocol() only has a distinct
        // unilateral template for tenant-initiated check-ins — a
        // landlord-initiated unilateral finalize would still render via
        // BILATERAL_CHECKIN (the only landlord-side template that exists),
        // i.e. an unlabelled unilateral document indistinguishable from a
        // real bilateral one. Rather than let that happen, this action
        // stays scoped to the case it was actually built (and templated) for.
        $canFinalizeBilateral = ! $isCompleted && $finalizeAction->canFinalize($protocol, false);
        $canFinalizeUnilateral = ! $isCompleted
            && ! $canFinalizeBilateral
            && $protocol->initiator_role === ParticipantRole::TENANT
            && $finalizeAction->canFinalize($protocol, true);

        // M11: same role-flip ProtocolSubmitController already computes at
        // submit time (property.declaration_type) — mirrored here purely to
        // label the draft-stage "send" field correctly before submit exists.
        $expectedCounterpartyRole = $protocol->property->declaration_type === DeclarationType::OWNER
            ? ParticipantRole::TENANT
            : ParticipantRole::LANDLORD;

        $document = $isCompleted
            ? GeneratedDocument::forProtocol($protocol)->ofType(DocumentType::PROTOCOL_PDF)->latestVersion()->first()
            : null;

        return Inertia::render('Protocol/Show', [
            'protocol' => [
                'id' => $protocol->id,
                'type' => $protocol->type->value,
                'type_label' => $protocol->type->label(),
                'status' => $protocol->status->value,
                'status_label' => $protocol->status->label(),
                'title' => $protocol->title,
                'is_draft' => $isDraft,
                'has_entitlement' => $entitlementService->hasEntitlement($user, $requiredAction),
                'dev_mode_available' => ! app()->environment('production'),
                'magic_link' => ! app()->environment('production') ? ($protocol->metadata['dev_magic_link'] ?? null) : null,
                'can_sign' => $canSign,
                'initiator_signed' => $initiatorParticipant?->hasSigned() ?? false,
                'counterparty_status_label' => $counterpartyParticipant?->participation_status_label,
                'is_completed' => $isCompleted,
                'completed_at' => $protocol->completed_at?->toIso8601String(),
                'can_finalize_bilateral' => $canFinalizeBilateral,
                'can_finalize_unilateral' => $canFinalizeUnilateral,
                'legal_mode_label' => $protocol->legal_mode?->label(),
                'is_unilateral_notice' => $isCompleted && $protocol->legal_mode !== null && ! $protocol->legal_mode->requiresBothSignatures(),
                'counterparty_role_label' => $expectedCounterpartyRole->label(),
                'document_hash' => $protocol->document_hash,
                'document_url' => $document !== null ? route('protocols.document', $protocol->id) : null,
                'qr_url' => $protocol->document_hash !== null ? route('qr.verify', ['hash' => $protocol->document_hash]) : null,
                'property' => [
                    'name' => $protocol->property->name,
                    'full_address' => $protocol->property->full_address,
                ],
                'initiator_name' => $protocol->createdBy->name,
                'created_at' => $protocol->created_at?->toIso8601String(),
                'rooms' => $protocol->rooms->map(fn (ProtocolRoom $room) => [
                    'id' => $room->id,
                    'display_name' => $room->display_name,
                    'catalog_item_id' => $room->catalog_item_id,
                    'custom_name' => $room->custom_name,
                    'items' => $room->items->map(fn (ProtocolItem $item) => [
                        'id' => $item->id,
                        'display_name' => $item->display_name,
                        'catalog_item_id' => $item->catalog_item_id,
                        'condition_catalog_item_id' => $item->condition_catalog_item_id,
                        'condition_name' => $item->condition_name,
                        'custom_name' => $item->custom_name,
                        'quantity' => $item->quantity,
                        'photos' => $item->photos->map(fn (Evidence $photo) => [
                            'id' => $photo->id,
                            'url' => route('protocols.evidence.show', [$protocol->id, $photo->id]),
                            'original_filename' => $photo->original_filename,
                            'human_size' => $photo->human_size,
                        ])->values()->all(),
                    ])->values()->all(),
                ])->values()->all(),
            ],
            'catalogs' => [
                'room_types' => $this->catalogOptions(CatalogItemType::ROOM),
                'item_templates' => $this->catalogOptions(CatalogItemType::ITEM),
                'condition_states' => $this->catalogOptions(CatalogItemType::CONDITION),
            ],
        ]);
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function catalogOptions(CatalogItemType $type): array
    {
        return CatalogItem::ofType($type)
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CatalogItem $item) => ['id' => $item->id, 'label' => $item->name])
            ->values()
            ->all();
    }
}
