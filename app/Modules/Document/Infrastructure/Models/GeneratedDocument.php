<?php

declare(strict_types=1);

namespace App\Modules\Document\Infrastructure\Models;

use App\Modules\Document\Domain\Enums\DocumentType;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GeneratedDocument extends Model
{
    use HasUuids;

    protected $fillable = [
        'protocol_id',
        'type',
        'template_type',
        'locale',
        'version',
        'filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'hash',
        'generated_at',
        'generated_by_user_id',
        'metadata',
    ];

    protected $attributes = [
        'disk' => 'minio',
        'locale' => 'pl',
        'version' => '1.0',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'size' => 'integer',
            'generated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    /**
     * Get URL to the document.
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
     * Get download filename.
     */
    public function getDownloadFilename(): string
    {
        return $this->filename;
    }

    /**
     * Scope: By type.
     */
    public function scopeOfType($query, DocumentType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: By protocol.
     */
    public function scopeForProtocol($query, Protocol|string $protocol)
    {
        $protocolId = $protocol instanceof Protocol ? $protocol->id : $protocol;

        return $query->where('protocol_id', $protocolId);
    }

    /**
     * Scope: Latest version.
     */
    public function scopeLatestVersion($query)
    {
        return $query->orderByDesc('version')->orderByDesc('generated_at');
    }
}
