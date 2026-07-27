<?php

declare(strict_types=1);

namespace App\Modules\Evidence\Application\Services;

use App\Modules\Evidence\Domain\Enums\EvidenceType;
use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EvidenceUploadService
{
    private string $disk = 'minio';

    public function __construct(
        private ?string $customDisk = null,
    ) {
        if ($customDisk) {
            $this->disk = $customDisk;
        }
    }

    /**
     * Upload evidence file with forensic data.
     */
    public function upload(
        UploadedFile $file,
        Protocol $protocol,
        User $uploader,
        ?Model $attachTo = null,
        ?string $caption = null,
        ?array $metadata = null,
        ?string $deviceInfo = null,
        ?string $clientIp = null,
    ): Evidence {
        $this->validateFile($file);

        $type = $this->determineType($file);
        $filename = $this->generateFilename($file);
        $path = $this->generatePath($protocol, $filename);

        // Read file content for hash calculation
        $content = file_get_contents($file->getRealPath());

        // Calculate SHA-256 hash for integrity
        $hash = hash('sha256', $content);

        // Store file
        Storage::disk($this->disk)->put($path, $content);

        // Extract image metadata if photo
        $fileMetadata = $metadata ?? [];
        $capturedAt = null;

        if ($type === EvidenceType::PHOTO) {
            $imageData = $this->extractImageMetadata($file);
            $fileMetadata = array_merge($fileMetadata, $imageData);

            // Extract captured_at from EXIF if available
            if (isset($imageData['exif']['datetime'])) {
                try {
                    $capturedAt = Carbon::parse($imageData['exif']['datetime']);
                } catch (\Exception $e) {
                    // Ignore parse errors
                }
            }
        }

        // Create evidence record with forensic data
        $evidence = Evidence::create([
            'protocol_id' => $protocol->id,
            'evidenceable_type' => $attachTo ? get_class($attachTo) : null,
            'evidenceable_id' => $attachTo?->getKey(),
            'uploaded_by_user_id' => $uploader->id,
            'type' => $type,
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => $this->disk,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'hash' => $hash,
            'captured_at' => $capturedAt,
            'device_info' => $deviceInfo,
            'server_received_at' => now(),
            'uploaded_from_ip' => $clientIp,
            'caption' => $caption,
            'metadata' => $fileMetadata ?: null,
        ]);

        // D7: Record upload event
        InspectionEvent::record(
            $protocol,
            InspectionEventType::PHOTO_UPLOADED,
            $protocol->getParticipantRole($uploader)?->value ?? 'initiator',
            $uploader->id,
            ['evidence_id' => $evidence->id, 'hash' => $hash],
            $clientIp
        );

        return $evidence;
    }

    /**
     * Upload multiple files.
     *
     * @return Evidence[]
     */
    public function uploadMultiple(
        array $files,
        Protocol $protocol,
        User $uploader,
        ?Model $attachTo = null,
    ): array {
        $evidences = [];
        $sortOrder = 0;

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $evidence = $this->upload($file, $protocol, $uploader, $attachTo);
                $evidence->update(['sort_order' => $sortOrder++]);
                $evidences[] = $evidence;
            }
        }

        return $evidences;
    }

    /**
     * Delete evidence and its file.
     */
    public function delete(Evidence $evidence, ?User $deletedBy = null, bool $forceDelete = false): bool
    {
        $protocol = $evidence->protocol;

        // D7: Record removal event before deletion
        if ($protocol) {
            InspectionEvent::record(
                $protocol,
                InspectionEventType::PHOTO_REMOVED,
                $deletedBy ? ($protocol->getParticipantRole($deletedBy)?->value ?? 'initiator') : 'system',
                $deletedBy?->id,
                ['evidence_id' => $evidence->id, 'hash' => $evidence->hash]
            );
        }

        if ($forceDelete) {
            Storage::disk($evidence->disk)->delete($evidence->path);

            return $evidence->forceDelete();
        }

        return $evidence->delete();
    }

    /**
     * Validate uploaded file.
     */
    private function validateFile(UploadedFile $file): void
    {
        $maxSize = config('evidence.max_file_size', 10 * 1024 * 1024); // 10MB default

        if ($file->getSize() > $maxSize) {
            throw new InvalidArgumentException(
                'File size exceeds maximum allowed: '.($maxSize / 1024 / 1024).'MB'
            );
        }

        $allowedMimes = $this->getAllowedMimeTypes();
        if (! in_array($file->getMimeType(), $allowedMimes)) {
            throw new InvalidArgumentException(
                'File type not allowed: '.$file->getMimeType()
            );
        }
    }

    /**
     * Determine evidence type from file.
     */
    private function determineType(UploadedFile $file): EvidenceType
    {
        $mime = $file->getMimeType();

        if (str_starts_with($mime, 'image/')) {
            return EvidenceType::PHOTO;
        }

        if ($mime === 'application/pdf') {
            return EvidenceType::DOCUMENT;
        }

        if (str_starts_with($mime, 'video/')) {
            return EvidenceType::VIDEO;
        }

        return EvidenceType::DOCUMENT;
    }

    /**
     * Generate unique filename.
     */
    private function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();

        return Str::uuid().'.'.$extension;
    }

    /**
     * Generate storage path.
     */
    private function generatePath(Protocol $protocol, string $filename): string
    {
        $date = now()->format('Y/m');

        return "protocols/{$protocol->id}/{$date}/{$filename}";
    }

    /**
     * Get all allowed MIME types.
     */
    private function getAllowedMimeTypes(): array
    {
        return array_merge(
            EvidenceType::PHOTO->allowedMimeTypes(),
            EvidenceType::DOCUMENT->allowedMimeTypes(),
            EvidenceType::VIDEO->allowedMimeTypes(),
        );
    }

    /**
     * Extract metadata from image.
     */
    private function extractImageMetadata(UploadedFile $file): array
    {
        $metadata = [];

        try {
            $imageSize = getimagesize($file->getRealPath());
            if ($imageSize) {
                $metadata['width'] = $imageSize[0];
                $metadata['height'] = $imageSize[1];
            }

            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($file->getRealPath());
                if ($exif) {
                    $metadata['exif'] = [
                        'make' => $exif['Make'] ?? null,
                        'model' => $exif['Model'] ?? null,
                        'datetime' => $exif['DateTime'] ?? null,
                        'orientation' => $exif['Orientation'] ?? null,
                    ];
                }
            }
        } catch (\Exception $e) {
            // Ignore metadata extraction errors
        }

        return $metadata;
    }

    /**
     * Set disk for storage.
     */
    public function setDisk(string $disk): self
    {
        $this->disk = $disk;

        return $this;
    }
}
