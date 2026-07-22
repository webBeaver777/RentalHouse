<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ReferenceDocument extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'protocol_id',
        'title',
        'description',
        'filename',
        'original_filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'hash',
        'document_date',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'document_date' => 'date',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected $attributes = [
        'disk' => 'minio',
    ];

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    /**
     * Get the URL to the file.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Get temporary URL.
     */
    public function getTemporaryUrl(int $minutes = 60): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $this->path,
            now()->addMinutes($minutes)
        );
    }

    /**
     * Check if this is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Check if this is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
