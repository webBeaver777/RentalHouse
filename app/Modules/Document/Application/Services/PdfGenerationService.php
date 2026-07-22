<?php

declare(strict_types=1);

namespace App\Modules\Document\Application\Services;

use App\Modules\Document\Domain\Enums\DocumentType;
use App\Modules\Document\Infrastructure\Models\GeneratedDocument;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service for generating PDF documents from protocols.
 */
final class PdfGenerationService
{
    private const DISK = 'minio';

    private const PATH_PREFIX = 'documents';

    /**
     * Generate protocol PDF document.
     */
    public function generateProtocolPdf(
        Protocol $protocol,
        ?User $generatedBy = null,
        string $locale = 'pl'
    ): GeneratedDocument {
        $protocol->load([
            'property',
            'rooms.items.photos',
            'meters',
            'keys',
            'defects.photos',
            'participants.user',
        ]);

        $data = $this->prepareProtocolData($protocol, $locale);
        $pdf = Pdf::loadView('documents.protocol', $data);

        return $this->saveDocument(
            $protocol,
            $pdf->output(),
            DocumentType::PROTOCOL_PDF,
            $generatedBy,
            $locale
        );
    }

    /**
     * Generate summary PDF (one-page overview).
     */
    public function generateSummaryPdf(
        Protocol $protocol,
        ?User $generatedBy = null,
        string $locale = 'pl'
    ): GeneratedDocument {
        $protocol->load([
            'property',
            'participants.user',
            'defects',
        ]);

        $data = $this->prepareSummaryData($protocol, $locale);
        $pdf = Pdf::loadView('documents.summary', $data);

        return $this->saveDocument(
            $protocol,
            $pdf->output(),
            DocumentType::SUMMARY_PDF,
            $generatedBy,
            $locale
        );
    }

    /**
     * Generate comparison PDF for check-out with baseline.
     */
    public function generateComparisonPdf(
        Protocol $checkOut,
        Protocol $checkIn,
        ?User $generatedBy = null,
        string $locale = 'pl'
    ): GeneratedDocument {
        $checkOut->load(['rooms.items.photos', 'rooms.items.baselineItem.photos']);
        $checkIn->load(['rooms.items.photos']);

        $data = $this->prepareComparisonData($checkOut, $checkIn, $locale);
        $pdf = Pdf::loadView('documents.comparison', $data);

        return $this->saveDocument(
            $checkOut,
            $pdf->output(),
            DocumentType::COMPARISON_PDF,
            $generatedBy,
            $locale
        );
    }

    /**
     * Get or generate protocol PDF.
     */
    public function getOrGenerateProtocolPdf(
        Protocol $protocol,
        ?User $generatedBy = null,
        string $locale = 'pl'
    ): GeneratedDocument {
        $existing = GeneratedDocument::forProtocol($protocol)
            ->ofType(DocumentType::PROTOCOL_PDF)
            ->where('locale', $locale)
            ->latestVersion()
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->generateProtocolPdf($protocol, $generatedBy, $locale);
    }

    /**
     * Prepare data for protocol template.
     */
    private function prepareProtocolData(Protocol $protocol, string $locale): array
    {
        return [
            'protocol' => $protocol,
            'property' => $protocol->property,
            'rooms' => $protocol->rooms,
            'meters' => $protocol->meters,
            'keys' => $protocol->keys,
            'defects' => $protocol->defects,
            'participants' => $protocol->participants,
            'locale' => $locale,
            'generated_at' => now(),
            'type_label' => $protocol->type->value === 'check_in'
                ? 'Protokół przekazania lokalu'
                : 'Protokół zdania lokalu',
        ];
    }

    /**
     * Prepare data for summary template.
     */
    private function prepareSummaryData(Protocol $protocol, string $locale): array
    {
        return [
            'protocol' => $protocol,
            'property' => $protocol->property,
            'participants' => $protocol->participants,
            'defects_count' => $protocol->defects->count(),
            'total_damage_cost' => $protocol->total_damage_cost,
            'deposit_amount' => $protocol->deposit_amount,
            'amount_to_return' => $protocol->amount_to_return,
            'locale' => $locale,
            'generated_at' => now(),
        ];
    }

    /**
     * Prepare data for comparison template.
     */
    private function prepareComparisonData(
        Protocol $checkOut,
        Protocol $checkIn,
        string $locale
    ): array {
        $comparisons = [];

        foreach ($checkOut->rooms as $room) {
            foreach ($room->items as $item) {
                if ($item->baselineItem) {
                    $comparisons[] = [
                        'room_name' => $room->display_name,
                        'item_name' => $item->display_name,
                        'checkout_condition' => $item->condition_name,
                        'checkin_condition' => $item->baselineItem->condition_name,
                        'condition_changed' => $item->condition_catalog_item_id !== $item->baselineItem->condition_catalog_item_id,
                        'checkout_photos' => $item->photos,
                        'checkin_photos' => $item->baselineItem->photos,
                    ];
                }
            }
        }

        return [
            'checkout' => $checkOut,
            'checkin' => $checkIn,
            'comparisons' => $comparisons,
            'locale' => $locale,
            'generated_at' => now(),
        ];
    }

    /**
     * Save generated document.
     */
    private function saveDocument(
        Protocol $protocol,
        string $content,
        DocumentType $type,
        ?User $generatedBy,
        string $locale
    ): GeneratedDocument {
        $filename = $this->generateFilename($protocol, $type, $locale);
        $path = self::PATH_PREFIX.'/'.$protocol->id.'/'.$filename;

        Storage::disk(self::DISK)->put($path, $content);

        return GeneratedDocument::create([
            'protocol_id' => $protocol->id,
            'type' => $type,
            'locale' => $locale,
            'filename' => $filename,
            'path' => $path,
            'disk' => self::DISK,
            'mime_type' => $type->mimeType(),
            'size' => strlen($content),
            'hash' => hash('sha256', $content),
            'generated_at' => now(),
            'generated_by_user_id' => $generatedBy?->id,
        ]);
    }

    /**
     * Generate filename for document.
     */
    private function generateFilename(
        Protocol $protocol,
        DocumentType $type,
        string $locale
    ): string {
        $typeSlug = match ($type) {
            DocumentType::PROTOCOL_PDF => 'protokol',
            DocumentType::SUMMARY_PDF => 'podsumowanie',
            DocumentType::COMPARISON_PDF => 'porownanie',
            DocumentType::EVIDENCE_ARCHIVE => 'dowody',
        };

        $propertySlug = Str::slug($protocol->property?->address ?? 'unknown');
        $date = now()->format('Y-m-d');

        return "{$typeSlug}_{$propertySlug}_{$date}.{$type->extension()}";
    }
}
