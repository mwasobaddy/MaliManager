<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Data\Organizations\ExportOrganizationsData;
use App\Models\Organization;
use App\Services\Service;
use App\Support\CsvExport;

/**
 * The organization CSV, matching the index's filters and sort.
 *
 * `scope` picks the row set: `all` ignores pagination, `page` exports only the
 * rows on the current page. Headers and column order are unchanged from the
 * in-controller version — a spreadsheet built against the old export keeps
 * importing.
 */
class ExportOrganizations extends Service
{
    public function handle(ExportOrganizationsData $data): CsvExport
    {
        $query = Organization::query()
            ->filtered([
                'search' => $data->search,
                'status' => $data->status,
            ])
            ->with(['plan', 'tenant'])
            ->withCount('users')
            ->orderBy('id');

        if ($data->scope === 'page') {
            $query->forPage($data->page, 15);
        }

        $rows = $query
            ->get()
            ->map(fn (Organization $organization): array => [
                $organization->id,
                $organization->name,
                $organization->slug,
                $organization->email ?? '',
                $organization->phone ?? '',
                // `status` is cast to the OrganizationStatus enum, which fputcsv
                // cannot stringify — use ->value.
                $organization->status->value,
                $organization->plan?->name ?? '',
                $organization->tenant?->domains()->value('domain') ?? '',
                $organization->users_count,
                $organization->created_at?->toDateTimeString() ?? '',
            ]);

        $suffix = $data->scope === 'page' ? '-page'.$data->page : '-all';

        return CsvExport::fromRows(
            'organizations'.$suffix.'.csv',
            ['ID', 'Name', 'Slug', 'Email', 'Phone', 'Status', 'Plan', 'Domain', 'Users', 'Created at'],
            $rows,
        );
    }
}
