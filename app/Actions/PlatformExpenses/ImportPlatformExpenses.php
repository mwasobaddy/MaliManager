<?php

declare(strict_types=1);

namespace App\Actions\PlatformExpenses;

use App\Data\PlatformExpenses\ImportPlatformExpensesData;
use App\Models\PlatformExpense;
use App\Services\Service;
use App\Support\CsvReader;
use App\Support\ImportResult;

/**
 * Create platform expenses from an uploaded CSV.
 *
 * Rows are independent: an unknown category, a non-numeric amount or a missing
 * date is recorded and skipped, so the file imports partially. That per-row
 * `continue` is what keeps the surrounding transaction from rolling good rows
 * back when a later row is bad.
 */
class ImportPlatformExpenses extends Service
{
    public function __construct(private CsvReader $csv) {}

    public function handle(ImportPlatformExpensesData $data): ImportResult
    {
        return $this->transaction(function () use ($data): ImportResult {
            $parsed = $this->csv->rows($data->file);
            $errors = [];
            $imported = 0;

            foreach ($parsed['rows'] as $row) {
                $rowData = $row['data'];
                $category = $rowData['category'] ?? '';
                $amount = $rowData['amount'] ?? '';
                $spentOn = $rowData['spenton'] ?? '';

                if (! in_array($category, PlatformExpense::CATEGORIES, true)) {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: unknown category \"{$category}\"."];

                    continue;
                }

                if ($amount === '' || ! is_numeric($amount) || (float) $amount < 0) {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: Amount must be a number of zero or more."];

                    continue;
                }

                if ($spentOn === '') {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: Spent On is required."];

                    continue;
                }

                PlatformExpense::create([
                    'category' => $category,
                    'amount' => $amount,
                    'currency' => strtoupper($rowData['currency'] ?? '') ?: 'KES',
                    'spent_on' => $spentOn,
                    'vendor_name' => ($rowData['vendorname'] ?? '') ?: null,
                    'notes' => ($rowData['notes'] ?? '') ?: null,
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
