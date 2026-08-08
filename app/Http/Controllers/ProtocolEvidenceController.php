<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * M10.3, Scenario A slice: serves an Evidence file to the browser.
 *
 * ⚠️ Deliberate design choice (see CLAUDE.md's "expected failure point"):
 * the app reaches MinIO on the internal container endpoint (minio:9000),
 * unreachable from the browser (needs localhost:9000). A signed/temporary
 * URL generated with that internal endpoint would embed a host the browser
 * can't resolve and would fail SigV4 host verification. Instead of juggling
 * dual endpoints, this route proxies: the server reads the object over the
 * internal endpoint (works fine) and streams the bytes back through the
 * app's own origin, authorized the same way as protocols.show.
 */
final class ProtocolEvidenceController extends Controller
{
    public function show(Protocol $protocol, Evidence $evidence): Response
    {
        abort_unless($protocol->created_by_user_id === Auth::id(), 403);
        abort_unless($evidence->protocol_id === $protocol->id, 404);

        $content = Storage::disk($evidence->disk)->get($evidence->path);
        abort_if($content === null, 404);

        return response($content, 200)
            ->header('Content-Type', $evidence->mime_type ?? 'application/octet-stream')
            ->header('Content-Disposition', 'inline; filename="'.addslashes($evidence->original_filename).'"')
            ->header('Cache-Control', 'private, max-age=3600');
    }
}
