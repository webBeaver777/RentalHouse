<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Document\Domain\Enums\DocumentType;
use App\Modules\Document\Infrastructure\Models\GeneratedDocument;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * M10.6, Scenario A slice: serves the frozen protocol PDF to the browser.
 * Same MinIO internal/external endpoint proxy pattern as
 * ProtocolEvidenceController (see its docblock) — streams through the app's
 * own origin rather than a signed MinIO URL. A GeneratedDocument only
 * exists once ProtocolFinalizeController has run, so no separate
 * completed-status check is needed here.
 */
final class ProtocolDocumentController extends Controller
{
    public function show(Protocol $protocol): Response
    {
        abort_unless($protocol->created_by_user_id === Auth::id(), 403);

        $document = GeneratedDocument::forProtocol($protocol)
            ->ofType(DocumentType::PROTOCOL_PDF)
            ->latestVersion()
            ->first();

        abort_if($document === null, 404);

        $content = Storage::disk($document->disk)->get($document->path);
        abort_if($content === null, 404);

        return response($content, 200)
            ->header('Content-Type', $document->mime_type ?? 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.addslashes($document->filename).'"')
            ->header('Cache-Control', 'private, max-age=3600');
    }
}
