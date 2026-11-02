<?php

declare(strict_types=1);

namespace App\Actions\Plans;

use App\Data\Plans\ExportPlansData;
use App\Models\Plan;
use App\Services\Service;
use App\Support\CsvExport;

/**
 * The plan CSV, honouring the index's own search and status so "Export all"
 * cannot disagree with the table.
 *
 * Headers, column order and the pipe-separated features cell are unchanged.
 */
class ExportPlans extends Service
{
    public function handle(ExportPlansData $data): CsvExport
    {
        $query = Plan::query()
            ->filtered([
                'search' => $data->search,
                'status' => $data->status,
            ])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($data->scope === 'page') {
            $query->forPage($data->page, 15);
        }

        $rows = $query->get()->map(fn (Plan $plan): array => [
            $plan->name,
            $plan->slug,
            $plan->description ?? '',
            $plan->currency,
            $plan->price,
            $plan->properties_limit ?? '',
            $plan->units_limit ?? '',
            // Pipe-separated so a features cell can never contain the
            // delimiter and split the row.
            implode('|', $plan->features ?? []),
            $plan->sort_order,
            $plan->is_active ? '1' : '0',
        ]);

        return CsvExport::fromRows(
            'plans.csv',
            ['Name', 'Slug', 'Description', 'Currency', 'Price', 'Properties limit', 'Units limit', 'Features', 'Sort order', 'Active'],
            $rows,
        );
    }
}
