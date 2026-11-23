<?php

namespace App\Actions\Leases;

use App\Data\Leases\ExportLeasesData;
use App\Models\Lease;
use App\Models\Property;
use App\Services\RentArrearsService;
use App\Services\Service;
use App\Support\CsvExport;

/**
 * The lease CSV, honouring the index's search, status, expiring, overdue
 * and sort filters. Balances are computed per exported lease, so the CSV
 * carries the same outstanding figures the table shows.
 */
class ExportLeases extends Service
{
    /** Column picker source: the sheet lists these, the download keeps the ticked ones. */
    public const HEADERS = ['ID', 'Unit', 'Occupant', 'Rent amount', 'Currency', 'Outstanding', 'Start', 'End', 'Status'];

    public function handle(Property $property, ExportLeasesData $data, RentArrearsService $arrears): CsvExport
    {
        $query = Lease::query()
            ->tenantIndex((int) $property->id, [
                'search' => $data->search,
                'status' => $data->status,
                'expiring' => $data->expiring,
                'overdue_ids' => $data->overdue
                    ? $arrears->overdueLeaseIds($property->organization, [$property->id])
                    : null,
                'sort' => $data->sort,
                'direction' => $data->direction,
            ])
            ->with(['person:id,first_name,last_name', 'unit:id,name']);

        if ($data->scope === 'page') {
            $query->forPage($data->page, 18);
        }

        $leases = $query->get();
        $balances = $arrears->balancesFor($leases);

        $rows = $leases->map(fn (Lease $lease): array => [
            $lease->id,
            $lease->unit?->name ?? '',
            trim(($lease->person?->first_name ?? '').' '.($lease->person?->last_name ?? '')),
            $lease->rent_amount,
            $lease->currency ?? '',
            $balances[$lease->id]['outstanding'] ?? '0.00',
            $lease->starts_at?->toDateString() ?? '',
            $lease->ends_at?->toDateString() ?? '',
            $lease->status,
        ]);

        $suffix = $data->scope === 'page' ? '-page'.$data->page : '-all';

        return CsvExport::fromSelectedColumns(
            'leases'.$suffix.'.csv',
            self::HEADERS,
            $rows,
            $data->columns,
        );
    }
}
