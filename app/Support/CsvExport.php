<?php

namespace App\Support;

/**
 * A finished CSV download: the filename and the exact bytes to send.
 *
 * Export actions return this instead of a response, so the CSV assembly stays
 * out of the HTTP layer while the controller keeps ownership of the download.
 * Encoding goes through `fputcsv` so headers, delimiter and quoting match what
 * the previous in-controller exports produced.
 */
final readonly class CsvExport
{
    public function __construct(
        public string $filename,
        public string $content,
    ) {}

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function fromRows(string $filename, array $headers, iterable $rows): self
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return new self($filename, $content);
    }
}
