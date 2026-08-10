<?php

declare(strict_types=1);

namespace App\Modules\Document\Application\Services;

use App\Modules\Billing\Application\Services\EntitlementService;
use App\Modules\Billing\Domain\Exceptions\EntitlementRequiredException;
use App\Modules\Document\Domain\Enums\DocumentType;
use App\Modules\Document\Domain\Enums\PdfTemplateType;
use App\Modules\Document\Infrastructure\Models\GeneratedDocument;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Infrastructure\Models\CounterpartyPhoto;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolComment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service for generating PDF documents from protocols.
 *
 * D3 HARD-GATE: Requires consumed entitlement to generate PDF.
 */
final class PdfGenerationService
{
    private const DISK = 'minio';

    private const PATH_PREFIX = 'documents';

    public function __construct(
        private readonly EntitlementService $entitlementService
    ) {}

    /**
     * Generate protocol PDF document.
     *
     * D3: Requires entitlement to generate PDF.
     * D4: Uses PdfTemplateType for template selection by type × initiator_role × legal_mode.
     *
     * @throws EntitlementRequiredException
     */
    public function generateProtocolPdf(
        Protocol $protocol,
        ?User $generatedBy = null,
        string $locale = 'pl',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): GeneratedDocument {
        // D3: HARD-GATE - require entitlement
        $this->requireEntitlement($protocol, $generatedBy, $ipAddress, $userAgent);

        // D4: Determine template type
        $templateType = PdfTemplateType::fromProtocol($protocol);

        // D7: Record PDF generation started
        InspectionEvent::record(
            $protocol,
            InspectionEventType::PDF_GENERATION_STARTED,
            $generatedBy ? 'initiator' : 'system',
            $generatedBy?->id,
            ['type' => DocumentType::PROTOCOL_PDF->value, 'template' => $templateType->value]
        );

        // Load all relations for PDF §17
        $protocol->load([
            'property',
            'rooms.items.photos',
            'rooms.items.baselineItem.photos', // For check-out comparison
            'meters',
            'keys',
            'defects.evidence',
            'participants.user',
            'linkedCheckIn.rooms.items', // For check-out comparison
        ]);

        $data = $this->prepareTemplateData($protocol, $templateType, $locale);
        $pdf = Pdf::loadView($templateType->viewName(), $data);

        $document = $this->saveDocument(
            $protocol,
            $pdf->output(),
            DocumentType::PROTOCOL_PDF,
            $generatedBy,
            $locale,
            $templateType
        );

        // D7: Record PDF generation completed
        InspectionEvent::record(
            $protocol,
            InspectionEventType::PDF_GENERATION_COMPLETED,
            'system',
            null,
            ['document_id' => $document->id, 'hash' => $document->hash, 'template' => $templateType->value]
        );

        return $document;
    }

    /**
     * @deprecated Use generateProtocolPdf() instead. Kept for backwards compatibility.
     *
     * @throws EntitlementRequiredException
     */
    public function generateSummaryPdf(
        Protocol $protocol,
        ?User $generatedBy = null,
        string $locale = 'pl',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): GeneratedDocument {
        return $this->generateProtocolPdf($protocol, $generatedBy, $locale, $ipAddress, $userAgent);
    }

    /**
     * @deprecated Use generateProtocolPdf() instead. Kept for backwards compatibility.
     *
     * @throws EntitlementRequiredException
     */
    public function generateComparisonPdf(
        Protocol $checkOut,
        Protocol $checkIn,
        ?User $generatedBy = null,
        string $locale = 'pl',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): GeneratedDocument {
        // Comparison is now handled automatically via PdfTemplateType::CHECKOUT_LANDLORD
        return $this->generateProtocolPdf($checkOut, $generatedBy, $locale, $ipAddress, $userAgent);
    }

    /**
     * Get or generate protocol PDF.
     *
     * D3: Requires entitlement if generating new PDF.
     *
     * @throws EntitlementRequiredException
     */
    public function getOrGenerateProtocolPdf(
        Protocol $protocol,
        ?User $generatedBy = null,
        string $locale = 'pl',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): GeneratedDocument {
        $existing = GeneratedDocument::forProtocol($protocol)
            ->ofType(DocumentType::PROTOCOL_PDF)
            ->where('locale', $locale)
            ->latestVersion()
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->generateProtocolPdf($protocol, $generatedBy, $locale, $ipAddress, $userAgent);
    }

    /**
     * Prepare data for template based on PdfTemplateType.
     *
     * D4 §17: Full PDF data including:
     * - property, type, initiator, counterparty, legal_mode
     * - all dates, rooms/elements/photos/comments
     * - meters, keys, defects, deductions, deposit calculation
     * - objections and counterparty photos, photo hashes, document_hash, QR
     */
    private function prepareTemplateData(
        Protocol $protocol,
        PdfTemplateType $templateType,
        string $locale
    ): array {
        // Base data for all templates
        $data = [
            'protocol' => $protocol,
            'property' => $protocol->property,
            'rooms' => $protocol->rooms,
            'meters' => $protocol->meters,
            'keys' => $protocol->keys,
            'defects' => $protocol->defects,
            'participants' => $protocol->participants,
            'locale' => $locale,
            'generated_at' => now(),
            'template_type' => $templateType,
            'document_hash' => $protocol->document_hash,
        ];

        // Comments for bilateral templates
        $data['comments'] = ProtocolComment::where('protocol_id', $protocol->id)
            ->orderBy('created_at')
            ->get();

        // Timeline (compressed for PDF)
        $data['timeline'] = InspectionEvent::where('protocol_id', $protocol->id)
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        // Check-out specific data
        if ($protocol->isCheckOut()) {
            // Objections for check-out protocols
            $data['objections'] = $protocol->objections()->orderBy('created_at')->get();

            // Counterparty photos
            $data['counterparty_photos'] = CounterpartyPhoto::where('protocol_id', $protocol->id)
                ->orderBy('uploaded_at')
                ->get();

            // Linked check-in for comparison
            if ($protocol->linked_checkin_id) {
                $data['linked_checkin'] = $protocol->linkedCheckIn;
            }

            // Deposit calculation
            $data['deposit_calculation'] = $this->calculateDepositData($protocol);
        }

        // QR URL for verification
        $data['qr_url'] = $this->generateQrUrl($protocol);

        return $data;
    }

    /**
     * Calculate deposit data for check-out PDF.
     */
    private function calculateDepositData(Protocol $protocol): array
    {
        $depositAmount = $protocol->deposit_amount ?? 0;
        $totalDeductions = $protocol->total_damage_cost ?? 0;
        $amountToReturn = max(0, $depositAmount - $totalDeductions);

        $deductions = [];
        foreach ($protocol->defects as $defect) {
            if (($defect->estimated_cost ?? 0) > 0) {
                $deductions[] = [
                    'description' => $defect->description ?? 'Usterka',
                    'amount' => $defect->estimated_cost,
                    'reason' => $defect->notes ?? null,
                ];
            }
        }

        return [
            'deposit_amount' => $depositAmount,
            'total_deductions' => $totalDeductions,
            'amount_to_return' => $amountToReturn,
            'deductions' => $deductions,
        ];
    }

    /**
     * Generate QR verification URL.
     */
    private function generateQrUrl(Protocol $protocol): ?string
    {
        if (! $protocol->document_hash) {
            return null;
        }

        // D5: Generate verification URL - route may not exist yet
        try {
            return route('qr.verify', ['hash' => $protocol->document_hash]);
        } catch (\Throwable) {
            // Route not defined yet, use fallback URL
            return config('app.url').'/verify/'.$protocol->document_hash;
        }
    }

    /**
     * Save generated document.
     *
     * D5: document_hash is frozen on first generation.
     */
    private function saveDocument(
        Protocol $protocol,
        string $content,
        DocumentType $type,
        ?User $generatedBy,
        string $locale,
        ?PdfTemplateType $templateType = null
    ): GeneratedDocument {
        $filename = $this->generateFilename($protocol, $type, $locale, $templateType);
        $path = self::PATH_PREFIX.'/'.$protocol->id.'/'.$filename;

        Storage::disk(self::DISK)->put($path, $content);

        $documentHash = hash('sha256', $content);

        // D5: Freeze document_hash on protocol if not already set
        if ($protocol->document_hash === null) {
            $protocol->document_hash = $documentHash;
            $protocol->save();
        }

        return GeneratedDocument::create([
            'protocol_id' => $protocol->id,
            'type' => $type,
            'locale' => $locale,
            'filename' => $filename,
            'path' => $path,
            'disk' => self::DISK,
            'mime_type' => $type->mimeType(),
            'size' => strlen($content),
            'hash' => $documentHash,
            'generated_at' => now(),
            'generated_by_user_id' => $generatedBy?->id,
            'template_type' => $templateType?->value,
        ]);
    }

    /**
     * Generate filename for document.
     */
    private function generateFilename(
        Protocol $protocol,
        DocumentType $type,
        string $locale,
        ?PdfTemplateType $templateType = null
    ): string {
        // Use template type for naming if available
        $typeSlug = match (true) {
            $templateType === PdfTemplateType::BILATERAL_CHECKIN => 'protokol_przekazania',
            $templateType === PdfTemplateType::UNILATERAL_TENANT_CHECKIN => 'dokumentacja_najemcy',
            $templateType === PdfTemplateType::CHECKOUT_LANDLORD => 'protokol_zwrotu',
            $templateType === PdfTemplateType::CHECKOUT_TENANT => 'dokumentacja_zwrotu',
            $templateType === PdfTemplateType::CHECKOUT_NO_BASELINE => 'zwrot_bez_baseline',
            $type === DocumentType::SUMMARY_PDF => 'podsumowanie',
            $type === DocumentType::COMPARISON_PDF => 'porownanie',
            $type === DocumentType::EVIDENCE_ARCHIVE => 'dowody',
            default => 'protokol',
        };

        $propertySlug = Str::slug($protocol->property?->address ?? 'unknown');
        $date = now()->format('Y-m-d');

        return "{$typeSlug}_{$propertySlug}_{$date}.{$type->extension()}";
    }

    /**
     * G6: Regenerate document (admin use only, bypasses entitlement).
     *
     * Generates a new version of the document without checking entitlements.
     * Used for admin PDF regeneration when original failed or needs update.
     */
    public function regenerateDocument(
        GeneratedDocument $document,
        ?User $generatedBy = null
    ): GeneratedDocument {
        /** @var Protocol|null $protocol */
        $protocol = $document->protocol;

        if ($protocol === null) {
            throw new \InvalidArgumentException('Dokument nie jest powiązany z protokołem.');
        }

        // Determine template type (use existing or detect)
        $templateType = $document->template_type
            ? PdfTemplateType::tryFrom($document->template_type)
            : PdfTemplateType::fromProtocol($protocol);

        $locale = $document->locale ?? 'pl';

        // D7: Record PDF regeneration started
        InspectionEvent::record(
            $protocol,
            InspectionEventType::PDF_GENERATION_STARTED,
            'admin',
            $generatedBy?->id,
            ['type' => 'regeneration', 'original_document_id' => $document->id]
        );

        // Load all relations for PDF §17
        $protocol->load([
            'property',
            'rooms.items.photos',
            'rooms.items.baselineItem.photos',
            'meters',
            'keys',
            'defects.evidence',
            'participants.user',
            'linkedCheckIn.rooms.items',
        ]);

        $data = $this->prepareTemplateData($protocol, $templateType, $locale);
        $pdf = Pdf::loadView($templateType->viewName(), $data);

        // Save with incremented version
        $newVersion = $this->getNextVersion($protocol, $document->type);

        $newDocument = $this->saveDocumentWithVersion(
            $protocol,
            $pdf->output(),
            $document->type,
            $generatedBy,
            $locale,
            $templateType,
            $newVersion
        );

        // D7: Record PDF regeneration completed
        InspectionEvent::record(
            $protocol,
            InspectionEventType::PDF_GENERATION_COMPLETED,
            'admin',
            $generatedBy?->id,
            ['document_id' => $newDocument->id, 'hash' => $newDocument->hash, 'regenerated' => true]
        );

        return $newDocument;
    }

    /**
     * Get next version number for a document type.
     */
    private function getNextVersion(Protocol $protocol, DocumentType $type): string
    {
        $latestVersion = GeneratedDocument::forProtocol($protocol)
            ->ofType($type)
            ->orderByDesc('version')
            ->value('version') ?? '1.0';

        $parts = explode('.', $latestVersion);
        $major = (int) $parts[0];
        $minor = (int) ($parts[1] ?? 0);

        return $major.'.'.($minor + 1);
    }

    /**
     * Save document with specific version (for regeneration).
     */
    private function saveDocumentWithVersion(
        Protocol $protocol,
        string $content,
        DocumentType $type,
        ?User $generatedBy,
        string $locale,
        ?PdfTemplateType $templateType,
        string $version
    ): GeneratedDocument {
        $filename = $this->generateFilename($protocol, $type, $locale, $templateType);
        $path = self::PATH_PREFIX.'/'.$protocol->id.'/v'.$version.'_'.$filename;

        Storage::disk(self::DISK)->put($path, $content);

        $documentHash = hash('sha256', $content);

        return GeneratedDocument::create([
            'protocol_id' => $protocol->id,
            'type' => $type,
            'locale' => $locale,
            'version' => $version,
            'filename' => $filename,
            'path' => $path,
            'disk' => self::DISK,
            'mime_type' => $type->mimeType(),
            'size' => strlen($content),
            'hash' => $documentHash,
            'generated_at' => now(),
            'generated_by_user_id' => $generatedBy?->id,
            'template_type' => $templateType?->value,
        ]);
    }

    /**
     * D3: HARD-GATE - require entitlement for PDF generation.
     *
     * @throws EntitlementRequiredException
     */
    private function requireEntitlement(
        Protocol $protocol,
        ?User $generatedBy,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        // Determine user to check entitlement for
        $user = $generatedBy ?? $protocol->initiator()?->user;

        if ($user === null) {
            throw new EntitlementRequiredException(
                'Brak użytkownika do weryfikacji uprawnień. Wymagana jest opłata.'
            );
        }

        // Get required action based on protocol type
        $action = EntitlementService::getRequiredAction($protocol);

        // Consume entitlement (this will check if already consumed)
        $this->entitlementService->consumeEntitlement(
            $user,
            $protocol,
            $action,
            $ipAddress,
            $userAgent
        );
    }
}
