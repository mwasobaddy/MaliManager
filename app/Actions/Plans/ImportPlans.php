<?php

declare(strict_types=1);

namespace App\Actions\Plans;

use App\Data\Plans\ImportPlansData;
use App\Models\Plan;
use App\Services\Service;
use App\Support\CsvReader;
use App\Support\ImportResult;

/**
 * Create plans from an uploaded CSV.
 *
 * Rows are independent: a bad row is recorded and skipped rather than aborting
 * the file, because a 30-tier CSV with one typo should not force a hand-edit of
 * the whole thing. That per-row continue is what keeps the surrounding
 * transaction from rolling the good rows back.
 */
class ImportPlans extends Service
{
    public function __construct(private CsvReader $csv) {}

    public function handle(ImportPlansData $data): ImportResult
    {
        return $this->transaction(function () use ($data): ImportResult {
            $parsed = $this->csv->rows($data->file);
            $errors = [];
            $imported = 0;

            foreach ($parsed['rows'] as $row) {
                $rowData = $row['data'];

                if (($rowData['name'] ?? '') === '' || ($rowData['slug'] ?? '') === '') {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: Name and Slug are required."];

                    continue;
                }

                if (Plan::withTrashed()->where('slug', $rowData['slug'])->exists()) {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: the slug \"{$rowData['slug']}\" is already taken."];

                    continue;
                }

                if (($rowData['price'] ?? '') !== '' && ! is_numeric($rowData['price'])) {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: Price must be a number."];

                    continue;
                }

                $features = array_values(array_filter(
                    array_map('trim', explode('|', $rowData['features'] ?? '')),
                ));

                Plan::create([
                    'name' => $rowData['name'],
                    'slug' => $rowData['slug'],
                    'description' => ($rowData['description'] ?? '') ?: null,
                    'currency' => strtoupper($rowData['currency'] ?? '') ?: 'KES',
                    'price' => (int) ($rowData['price'] ?? 0),
                    'properties_limit' => ($rowData['propertieslimit'] ?? '') === '' ? null : (int) $rowData['propertieslimit'],
                    'units_limit' => ($rowData['unitslimit'] ?? '') === '' ? null : (int) $rowData['unitslimit'],
                    'features' => $features,
                    'sort_order' => (int) ($rowData['sortorder'] ?? 0),
                    'is_active' => ! isset($rowData['active']) || $rowData['active'] === '' || in_array(strtolower($rowData['active']), ['1', 'true', 'yes', 'active'], true),
                    'created_by' => $data->actor?->id,
                ]);

                $imported++;
            }

            if (! $parsed['ok']) {
                $errors = $parsed['rows'];
            }

            return new ImportResult($imported, count($errors), $errors);
        });
    }
}
