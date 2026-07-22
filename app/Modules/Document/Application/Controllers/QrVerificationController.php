<?php

declare(strict_types=1);

namespace App\Modules\Document\Application\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Document\Infrastructure\Models\GeneratedDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * D5: QR verification controller.
 *
 * Public page showing document verification WITHOUT PII.
 */
class QrVerificationController extends Controller
{
    /**
     * Show QR verification page.
     *
     * Does NOT show: party names, private photos.
     * Shows: document existence, generation date, type, legal_mode, document_hash, signature status.
     */
    public function show(string $hash): Response
    {
        $document = GeneratedDocument::where('hash', $hash)->first();

        if (! $document) {
            return Inertia::render('Document/QrNotFound', [
                'hash' => $hash,
            ]);
        }

        $protocol = $document->protocol;

        return Inertia::render('Document/QrVerification', [
            // Document info (public)
            'document_exists' => true,
            'document_hash' => $document->hash,
            'generated_at' => $document->generated_at->format('d.m.Y H:i'),
            'document_type' => $document->type->label(),

            // Protocol info (anonymized, no PII)
            'protocol_type' => $protocol->type->value,
            'protocol_type_label' => $protocol->isCheckIn() ? 'Protokół wjazdowy' : 'Protokół wyjazdowy',
            'legal_mode' => $protocol->legal_mode?->value,
            'legal_mode_label' => $protocol->legal_mode?->label(),

            // Signature status (without names)
            'initiator_signed' => $protocol->initiator()?->hasSigned() ?? false,
            'counterparty_signed' => $protocol->counterparty()?->hasSigned() ?? false,
            'all_signed' => $protocol->allParticipantsSigned(),

            // Status info
            'protocol_status' => $protocol->status->value,
            'protocol_status_label' => $protocol->status->label(),

            // Dates (anonymized)
            'protocol_created_at' => $protocol->created_at->format('d.m.Y'),
            'protocol_completed_at' => $protocol->completed_at?->format('d.m.Y'),
        ]);
    }

    /**
     * API endpoint for QR verification (JSON).
     */
    public function verify(Request $request): JsonResponse
    {
        $hash = $request->input('hash');

        if (! $hash || strlen($hash) !== 64) {
            return response()->json([
                'valid' => false,
                'error' => 'Nieprawidłowy hash dokumentu',
            ], 400);
        }

        $document = GeneratedDocument::where('hash', $hash)->first();

        if (! $document) {
            return response()->json([
                'valid' => false,
                'error' => 'Dokument nie został znaleziony',
            ]);
        }

        $protocol = $document->protocol;

        return response()->json([
            'valid' => true,
            'document' => [
                'hash' => $document->hash,
                'type' => $document->type->label(),
                'generated_at' => $document->generated_at->toIso8601String(),
            ],
            'protocol' => [
                'type' => $protocol->isCheckIn() ? 'check_in' : 'check_out',
                'legal_mode' => $protocol->legal_mode?->value,
                'status' => $protocol->status->value,
                'all_signed' => $protocol->allParticipantsSigned(),
            ],
        ]);
    }
}
