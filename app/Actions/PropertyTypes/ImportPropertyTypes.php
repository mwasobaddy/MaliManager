<?php

declare(strict_types=1);

namespace App\Actions\PropertyTypes;

use App\Data\PropertyTypes\ImportPropertyTypesData;
use App\Enums\PropertyCategory;
use App\Models\PropertyType;
use App\Services\Service;
use App\Support\CsvReader;
use App\Support\ImportResult;
use App\Support\PropertyTypeCatalog;

/**
 * Create property types from an uploaded CSV.
 *
 * Rows are independent: a bad row is recorded and skipped while the good rows
 * still land. A duplicate name is reported rather than upserted — the file was
 * probably exported from here and re-imported, and quietly rewriting a curated
 * category from a spreadsheet is worse than a clear error.
 */
class ImportPropertyTypes extends Service
{
    public function __construct(private CsvReader $csv) {}

    public function handle(ImportPropertyTypesData $data): ImportResult
    {
        return $this->transaction(function () use ($data): ImportResult {
            $parsed = $this->csv->rows($data->file);
            $errors = [];
            $imported = 0;

            foreach ($parsed['rows'] as $row) {
                $rowData = $row['data'];
                $name = trim($rowData['name'] ?? '');

                if ($name === '') {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: Name is required."];

                    continue;
                }

                if (PropertyType::withTrashed()->where('name', $name)->exists()) {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: \"{$name}\" already exists."];

                    continue;
                }

                $category = strtolower(trim($rowData['category'] ?? '')) ?: PropertyCategory::Residential->value;

                if (! PropertyCategory::tryFrom($category)) {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: category must be residential or commercial."];

                    continue;
                }

                PropertyType::create([
                    'name' => $name,
                    'slug' => PropertyTypeCatalog::slugFor($name),
                    'category' => $category,
                    'sort_order' => (int) PropertyType::query()->max('sort_order') + 1,
                    'status' => strtolower(trim($rowData['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active',
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
