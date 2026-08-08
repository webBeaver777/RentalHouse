<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesDraftProtocolMutation;
use App\Modules\Evidence\Application\Services\EvidenceUploadService;
use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * M10.3, Scenario A slice: photo upload/removal for an item on a draft
 * check-in protocol.
 *
 * Backend (Evidence model, EvidenceUploadService — SHA-256 hashing, MIME/
 * size validation, MinIO storage) is frozen and already covered by
 * EvidenceTest / EvidenceUploadServiceTest — this controller only wires the
 * upload endpoint through the existing service, guarded to draft-only +
 * initiator (see AuthorizesDraftProtocolMutation). The file is NEVER
 * written outside EvidenceUploadService — that's the only place the
 * SHA-256 hash gets computed.
 */
final class ProtocolItemPhotoController extends Controller
{
    use AuthorizesDraftProtocolMutation;

    public function store(
        Request $request,
        Protocol $protocol,
        ProtocolRoom $room,
        ProtocolItem $item,
        EvidenceUploadService $evidenceUploadService
    ): RedirectResponse {
        $this->assertMutable($protocol);
        abort_unless($room->protocol_id === $protocol->id, 404);
        abort_unless($item->protocol_room_id === $room->id, 404);

        $request->validate(['photo' => ['required', 'file']]);

        /** @var UploadedFile $file */
        $file = $request->file('photo');

        /** @var User $user */
        $user = $request->user();

        try {
            $evidenceUploadService->upload(
                $file,
                $protocol,
                $user,
                attachTo: $item,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'photo' => $this->translateUploadError($e->getMessage()),
            ]);
        }

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Zdjęcie zostało dodane.');
    }

    public function destroy(
        Request $request,
        Protocol $protocol,
        ProtocolRoom $room,
        ProtocolItem $item,
        Evidence $evidence,
        EvidenceUploadService $evidenceUploadService
    ): RedirectResponse {
        $this->assertMutable($protocol);
        abort_unless($room->protocol_id === $protocol->id, 404);
        abort_unless($item->protocol_room_id === $room->id, 404);
        abort_unless(
            $evidence->evidenceable_type === ProtocolItem::class && $evidence->evidenceable_id === $item->id,
            404
        );

        /** @var User $user */
        $user = $request->user();

        $evidenceUploadService->delete($evidence, $user);

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Zdjęcie zostało usunięte.');
    }

    /**
     * EvidenceUploadService throws English messages — translate the two
     * known cases to Polish for the UI; fall back to a generic message.
     */
    private function translateUploadError(string $message): string
    {
        return match (true) {
            str_contains($message, 'File size exceeds maximum') => 'Plik jest za duży (maksymalny rozmiar: 10MB).',
            str_contains($message, 'File type not allowed') => 'Niedozwolony typ pliku. Dozwolone: zdjęcia (JPG, PNG, WEBP, HEIC).',
            default => 'Nie udało się przesłać pliku.',
        };
    }
}
