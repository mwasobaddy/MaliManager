<?php

namespace App\Actions\Properties;

use App\Data\Units\ExportUnitsData;
use App\Models\Unit;
use App\Services\Service;
use App\Support\CsvExport;
use App\Support\TenancyContext;

/**
 * The unit CSV, honouring the index's search, status, property and sort
 * filters. Photos export as a count — a CSV cannot carry image files, so
 * bulk photo intake stays a separate ZIP-based design if it ever ships.
 */
class ExportUnits extends Service
{
    /** Column picker source: the sheet lists these, the download keeps the ticked ones. */
    public const HEADERS = ['ID', 'Name', 'Type', 'Billing frequency', 'Rent amount', 'Status', 'Property', 'Photos', 'Created at'];

    public function handle(ExportUnitsData $data): CsvExport
    {
        $query = Unit::query()
            ->tenantIndex((int) TenancyContext::organization()?->id, [
                'search' => $data->search,
                'status' => $data->status,
                'property' => $data->property,
                'sort' => $data->sort,
                'direction' => $data->direction,
            ])
            ->with(['property:id,name', 'media']);

        if ($data->scope === 'page') {
            $query->forPage($data->page, 18);
        }

        $rows = $query->get()->map(fn (Unit $unit): array => [
            $unit->id,
            $unit->name,
            $unit->type ?? '',
            $unit->rent_frequency ?? 'monthly',
            $unit->rent_amount,
            $unit->status,
            $unit->property?->name ?? '',
            $unit->getMedia('photos')->count(),
            $unit->created_at?->toDateTimeString() ?? '',
        ]);

        $suffix = $data->scope === 'page' ? '-page'.$data->page : '-all';

        return CsvExport::fromSelectedColumns(
            'units'.$suffix.'.csv',
            self::HEADERS,
            $rows,
            $data->columns,
        );
    }
}
