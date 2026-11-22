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

    /**
     * Same as fromRows, but only the requested headers survive — in the
     * sheet's order, not the export's. Unknown names are ignored rather
     * than failing the download, because a stale bookmarked column list
     * should degrade to fewer columns, not an error page.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $selected
     */
    public static function fromSelectedColumns(string $filename, array $headers, iterable $rows, array $selected): self
    {
        if ($selected === []) {
            return self::fromRows($filename, $headers, $rows);
        }

        $indexes = [];

        foreach ($selected as $name) {
            $index = array_search($name, $headers, true);

            if ($index !== false && ! in_array($index, $indexes, true)) {
                $indexes[] = $index;
            }
        }

        if ($indexes === []) {
            return self::fromRows($filename, $headers, $rows);
        }

        $keptHeaders = array_map(fn (int $index): string => $headers[$index], $indexes);
        $keptRows = [];

        foreach ($rows as $row) {
            $keptRows[] = array_map(fn (int $index): mixed => array_values($row)[$index] ?? '', $indexes);
        }

        return self::fromRows($filename, $keptHeaders, $keptRows);
    }
}
