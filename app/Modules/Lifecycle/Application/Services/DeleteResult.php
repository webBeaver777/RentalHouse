<?php

declare(strict_types=1);

namespace App\Modules\Lifecycle\Application\Services;

/**
 * Result of a RODO deletion operation.
 */
final class DeleteResult
{
    public bool $userAnonymized = false;

    public int $anonymizedCount = 0;

    public int $deletedFilesCount = 0;

    public array $errors = [];

    public function toArray(): array
    {
        return [
            'user_anonymized' => $this->userAnonymized,
            'anonymized_records' => $this->anonymizedCount,
            'deleted_files' => $this->deletedFilesCount,
            'errors' => $this->errors,
        ];
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }
}
