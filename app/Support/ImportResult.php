<?php

namespace App\Support;

/**
 * The outcome of a bulk import: how many rows landed, how many did not, and a
 * per-row reason for each failure.
 *
 * Mirrors the array shape the import services returned, so the existing
 * per-row reporting sheets and the toast wording keep working unchanged.
 */
final readonly class ImportResult
{
    /**
     * @param  array<int, array{row: int|string, message: string}>  $errors
     */
    public function __construct(
        public int $imported,
        public int $failed,
        public array $errors,
    ) {}

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }

    /**
     * @return array{imported: int, failed: int, errors: array<int, array{row: int|string, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'imported' => $this->imported,
            'failed' => $this->failed,
            'errors' => $this->errors,
        ];
    }
}
