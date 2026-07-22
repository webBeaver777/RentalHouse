<?php

namespace App\Modules\Evidence\Infrastructure\Models;

use App\Modules\Evidence\Domain\Enums\EvidenceType;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Evidence extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'evidences';

    protected $fillable = [
        'protocol_id',
        'evidenceable_type',
        'evidenceable_id',
        'uploaded_by_user_id',
        'type',
        'filename',
        'original_filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'caption',
        'sort_order',
        'metadata',
    ];

    protected $attributes = [
        'disk' => 'minio',
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => EvidenceType::class,
            'size' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the protocol this evidence belongs to.
     */
    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    /**
     * Get the user who uploaded this evidence.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * Get the parent model (ProtocolItem, ProtocolRoom, etc.).
     */
    public function evidenceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the full URL to the file.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Get temporary URL (for private files).
     */
    public function getTemporaryUrl(int $minutes = 60): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $this->path,
            now()->addMinutes($minutes)
        );
    }

    /**
     * Get human-readable file size.
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if this is an image.
     */
    public function isImage(): bool
    {
        return $this->type === EvidenceType::PHOTO;
    }

    /**
     * Check if this is a document.
     */
    public function isDocument(): bool
    {
        return $this->type === EvidenceType::DOCUMENT;
    }

    /**
     * Check if this is a video.
     */
    public function isVideo(): bool
    {
        return $this->type === EvidenceType::VIDEO;
    }

    /**
     * Delete the file from storage when model is deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (Evidence $evidence) {
            if ($evidence->isForceDeleting()) {
                Storage::disk($evidence->disk)->delete($evidence->path);
            }
        });
    }

    /**
     * Scope: Only photos.
     */
    public function scopePhotos($query)
    {
        return $query->where('type', EvidenceType::PHOTO);
    }

    /**
     * Scope: Only documents.
     */
    public function scopeDocuments($query)
    {
        return $query->where('type', EvidenceType::DOCUMENT);
    }

    /**
     * Scope: By protocol.
     */
    public function scopeForProtocol($query, Protocol|string $protocol)
    {
        $protocolId = $protocol instanceof Protocol ? $protocol->id : $protocol;
        return $query->where('protocol_id', $protocolId);
    }
}
