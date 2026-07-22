<?php

namespace App\Modules\Evidence\Application\Services;

use App\Modules\Evidence\Domain\Enums\EvidenceType;
use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
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
     * Upload evidence file.
     */
    public function upload(
        UploadedFile $file,
        Protocol $protocol,
        User $uploader,
        ?Model $attachTo = null,
        ?string $caption = null,
        ?array $metadata = null,
    ): Evidence {
        $this->validateFile($file);

        $type = $this->determineType($file);
        $filename = $this->generateFilename($file);
        $path = $this->generatePath($protocol, $filename);

        // Store file
        Storage::disk($this->disk)->put($path, file_get_contents($file->getRealPath()));

        // Extract image metadata if photo
        $fileMetadata = $metadata ?? [];
        if ($type === EvidenceType::PHOTO) {
            $fileMetadata = array_merge($fileMetadata, $this->extractImageMetadata($file));
        }

        // Create evidence record
        return Evidence::create([
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
            'caption' => $caption,
            'metadata' => $fileMetadata ?: null,
        ]);
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
    public function delete(Evidence $evidence, bool $forceDelete = false): bool
    {
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
                "File size exceeds maximum allowed: " . ($maxSize / 1024 / 1024) . "MB"
            );
        }

        $allowedMimes = $this->getAllowedMimeTypes();
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new InvalidArgumentException(
                "File type not allowed: " . $file->getMimeType()
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
        return Str::uuid() . '.' . $extension;
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

            $exif = @exif_read_data($file->getRealPath());
            if ($exif) {
                $metadata['exif'] = [
                    'make' => $exif['Make'] ?? null,
                    'model' => $exif['Model'] ?? null,
                    'datetime' => $exif['DateTime'] ?? null,
                    'orientation' => $exif['Orientation'] ?? null,
                ];
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
