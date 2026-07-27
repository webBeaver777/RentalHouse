<?php

declare(strict_types=1);

namespace App\Modules\Document\Application\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Document\Infrastructure\Models\GeneratedDocument;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * D5: QR verification controller.
 *
 * Public page showing document verification WITHOUT PII.
 * Uses Blade templates for public Polish pages.
 */
class QrVerificationController extends Controller
{
    /**
     * Show QR verification page.
     *
     * Does NOT show: party names, private photos.
     * Shows: document existence, generation date, type, legal_mode, document_hash, signature status.
     */
    public function show(string $hash): View
    {
        // Try to find by document hash first
        $document = GeneratedDocument::where('hash', $hash)->first();

        if ($document) {
            $protocol = $document->protocol;

            return view('qr.verification', [
                'found' => true,
                'document_hash' => $document->hash,
                'generated_at' => $document->generated_at->format('d.m.Y H:i'),
                'document_type' => $document->type->label(),
                'protocol_type_label' => $protocol->isCheckIn() ? 'Protokół wjazdowy' : 'Protokół wyjazdowy',
                'legal_mode_label' => $protocol->legal_mode?->label() ?? 'Nieokreślony',
                'initiator_signed' => $protocol->initiator()?->hasSigned() ?? false,
                'counterparty_signed' => $protocol->counterparty()?->hasSigned() ?? false,
                'all_signed' => $protocol->allParticipantsSigned(),
                'protocol_status_label' => $protocol->status->label(),
                'protocol_created_at' => $protocol->created_at->format('d.m.Y'),
                'protocol_completed_at' => $protocol->completed_at?->format('d.m.Y'),
            ]);
        }

        // Try to find by protocol document_hash
        $protocol = Protocol::where('document_hash', $hash)->first();

        if ($protocol) {
            return view('qr.verification', [
                'found' => true,
                'document_hash' => $protocol->document_hash,
                'generated_at' => $protocol->updated_at->format('d.m.Y H:i'),
                'document_type' => 'Protokół',
                'protocol_type_label' => $protocol->isCheckIn() ? 'Protokół wjazdowy' : 'Protokół wyjazdowy',
                'legal_mode_label' => $protocol->legal_mode?->label() ?? 'Nieokreślony',
                'initiator_signed' => $protocol->initiator()?->hasSigned() ?? false,
                'counterparty_signed' => $protocol->counterparty()?->hasSigned() ?? false,
                'all_signed' => $protocol->allParticipantsSigned(),
                'protocol_status_label' => $protocol->status->label(),
                'protocol_created_at' => $protocol->created_at->format('d.m.Y'),
                'protocol_completed_at' => $protocol->completed_at?->format('d.m.Y'),
            ]);
        }

        return view('qr.not-found', [
            'hash' => $hash,
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
