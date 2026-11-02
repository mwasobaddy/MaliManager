<?php

declare(strict_types=1);

namespace App\Actions\PlatformExpenses;

use App\Data\PlatformExpenses\ExportPlatformExpensesData;
use App\Models\PlatformExpense;
use App\Services\Service;
use App\Support\CsvExport;

/**
 * The platform expense CSV, honouring the index's search, category and date
 * range so "Export all" cannot disagree with the table.
 *
 * Headers and column order are unchanged; `amount` is read with
 * `getRawOriginal()` so the decimal string is written exactly as stored rather
 * than re-formatted by the cast.
 */
class ExportPlatformExpenses extends Service
{
    public function handle(ExportPlatformExpensesData $data): CsvExport
    {
        $query = PlatformExpense::query()
            ->filtered([
                'search' => $data->search,
                'category' => $data->category,
                'from' => $data->from,
                'to' => $data->to,
            ])
            ->orderByDesc('spent_on')
            ->orderBy('id');

        if ($data->scope === 'page') {
            $query->forPage($data->page, 15);
        }

        $rows = $query->get()->map(fn (PlatformExpense $expense): array => [
            $expense->spent_on?->format('Y-m-d') ?? '',
            $expense->category,
            // `amount` is cast to `decimal:2`, which stringifies; the raw
            // attribute is already the string the CSV wants.
            $expense->getRawOriginal('amount'),
            $expense->currency,
            $expense->vendor_name ?? '',
            $expense->notes ?? '',
        ]);

        return CsvExport::fromRows(
            'platform-expenses.csv',
            ['Spent on', 'Category', 'Amount', 'Currency', 'Vendor', 'Notes'],
            $rows,
        );
    }
}
