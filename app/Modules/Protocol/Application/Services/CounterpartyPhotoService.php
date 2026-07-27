<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Application\Services;

use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Infrastructure\Models\CounterpartyPhoto;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * D6: Service for managing counterparty photos.
 *
 * Allows counterparty to upload photos during review.
 * Photos are stored in MinIO with SHA-256 hash verification.
 */
final class CounterpartyPhotoService
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private string $disk = 'minio';

    /**
     * Upload a photo from counterparty.
     */
    public function upload(
        Protocol $protocol,
        Participant $uploader,
        UploadedFile $file,
        ?ProtocolRoom $room = null,
        ?ProtocolItem $item = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): CounterpartyPhoto {
        $this->validateFile($file);

        // Generate unique filename and path
        $filename = $this->generateFilename($file);
        $path = $this->generatePath($protocol, $filename);

        // Calculate hash before upload
        $hash = hash_file('sha256', $file->getPathname());

        // Store file
        Storage::disk($this->disk)->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        // Create record
        $photo = CounterpartyPhoto::create([
            'protocol_id' => $protocol->id,
            'uploaded_by_role' => $uploader->role,
            'uploaded_by_user_id' => $uploader->user_id,
            'room_id' => $room?->id,
            'item_id' => $item?->id,
            'path' => $path,
            'disk' => $this->disk,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'hash' => $hash,
            'uploaded_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        // D7: Record upload event
        InspectionEvent::record(
            $protocol,
            InspectionEventType::COUNTERPARTY_PHOTO_UPLOADED,
            $uploader->role->value,
            $uploader->user_id,
            [
                'photo_id' => $photo->id,
                'hash' => $hash,
                'room_id' => $room?->id,
                'item_id' => $item?->id,
            ],
            $ipAddress,
            $userAgent
        );

        return $photo;
    }

    /**
     * Get all photos for a protocol.
     */
    public function getPhotosForProtocol(Protocol $protocol): Collection
    {
        return CounterpartyPhoto::where('protocol_id', $protocol->id)
            ->orderBy('uploaded_at', 'asc')
            ->get();
    }

    /**
     * Get photos for a specific room.
     */
    public function getPhotosForRoom(Protocol $protocol, ProtocolRoom $room): Collection
    {
        return CounterpartyPhoto::where('protocol_id', $protocol->id)
            ->forRoom((string) $room->id)
            ->orderBy('uploaded_at', 'asc')
            ->get();
    }

    /**
     * Get photos for a specific item.
     */
    public function getPhotosForItem(Protocol $protocol, ProtocolItem $item): Collection
    {
        return CounterpartyPhoto::where('protocol_id', $protocol->id)
            ->forItem($item->id)
            ->orderBy('uploaded_at', 'asc')
            ->get();
    }

    /**
     * Delete a photo.
     */
    public function delete(
        CounterpartyPhoto $photo,
        Participant $deletedBy,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): bool {
        $protocol = $photo->protocol;

        // D7: Record removal event
        if ($protocol !== null) {
            InspectionEvent::record(
                $protocol,
                InspectionEventType::PHOTO_REMOVED,
                $deletedBy->role->value,
                $deletedBy->user_id,
                [
                    'photo_id' => $photo->id,
                    'hash' => $photo->hash,
                ],
                $ipAddress,
                $userAgent
            );
        }

        // Delete from storage
        Storage::disk($photo->disk)->delete($photo->path);

        return $photo->delete();
    }

    /**
     * Verify all photo hashes for a protocol.
     */
    public function verifyAllHashes(Protocol $protocol): array
    {
        $photos = $this->getPhotosForProtocol($protocol);
        $results = [];

        foreach ($photos as $photo) {
            $results[$photo->id] = [
                'filename' => $photo->filename,
                'hash' => $photo->hash,
                'valid' => $photo->verifyHash(),
            ];
        }

        return $results;
    }

    /**
     * Validate uploaded file.
     */
    private function validateFile(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException(
                'Plik jest zbyt duży. Maksymalny rozmiar to 10MB.'
            );
        }

        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException(
                'Nieprawidłowy typ pliku. Dozwolone: JPG, PNG, WebP.'
            );
        }
    }

    /**
     * Generate unique filename.
     */
    private function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();

        return Str::uuid()->toString().'.'.$extension;
    }

    /**
     * Generate storage path.
     */
    private function generatePath(Protocol $protocol, string $filename): string
    {
        $date = now()->format('Y/m');

        return "counterparty-photos/{$protocol->id}/{$date}/{$filename}";
    }
}
