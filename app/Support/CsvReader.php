<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Reads an uploaded CSV into normalised, per-row records.
 *
 * Shared by every platform CSV importer so the awkward parts live in one place:
 * an unreadable file, a file with no header row, the `fgetcsv` escape
 * deprecation, and header matching.
 *
 * Header names are matched case-insensitively with spaces, underscores and
 * punctuation removed, so "First Name", `first_name` and `FIRSTNAME` all resolve
 * to the same key. That means each caller can look up `firstname` without
 * caring how the file was actually spelled.
 */
class CsvReader
{
    /**
     * @return array{
     *     ok: bool,
     *     error: string|null,
     *     rows: list<array{row: int, data: array<string, string>}>,
     *     columns: list<string>
     * }
     */
    public function rows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return $this->failure('The uploaded file could not be read.');
        }

        $header = fgetcsv($handle, escape: '');

        if ($header === false || $header === [null]) {
            fclose($handle);

            return $this->failure('The CSV has no header row.');
        }

        $columns = array_map($this->normalise(...), $header);

        $rows = [];
        // Start at 2 so the number matches what a spreadsheet shows: line 1 is
        // the header, so the first data row is line 2.
        $line = 1;

        while (($values = fgetcsv($handle, escape: '')) !== false) {
            $line++;

            // A blank line is `[null]`, which is not a row of data.
            if ($values === [null] || $values === []) {
                continue;
            }

            $values = array_pad($values, count($columns), null);

            $record = [];

            foreach ($columns as $index => $column) {
                $record[$column] = trim((string) ($values[$index] ?? ''));
            }

            $rows[] = ['row' => $line, 'data' => $record];
        }

        fclose($handle);

        return ['ok' => true, 'error' => null, 'rows' => $rows, 'columns' => $columns];
    }

    /**
     * "First Name" / `first_name` / "FIRSTNAME" -> `firstname`.
     */
    private function normalise(string $column): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $column) ?? '');
    }

    /**
     * @return array{ok: false, error: string, rows: array<int, array{row: int, message: string}>, columns: list<string>}
     */
    private function failure(string $message): array
    {
        return [
            'ok' => false,
            'error' => $message,
            'rows' => [['row' => 0, 'message' => $message]],
            'columns' => [],
        ];
    }
}
