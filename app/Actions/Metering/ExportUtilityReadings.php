<?php

namespace App\Actions\Metering;

use App\Data\Metering\ExportUtilityReadingsData;
use App\Enums\UtilityType;
use App\Models\UtilityReading;
use App\Services\Service;
use App\Support\CsvExport;
use App\Support\TenancyContext;

/**
 * The utility-readings CSV, honouring the index's unit, utility, search
 * and sort filters. Consumption exports as stored — a number that already
 * appeared on an invoice is never recomputed.
 */
class ExportUtilityReadings extends Service
{
    /** Column picker source: the sheet lists these, the download keeps the ticked ones. */
    public const HEADERS = ['ID', 'Unit', 'Utility', 'Reading', 'Unit of measure', 'Consumption', 'Read on', 'Recorded by'];

    public function handle(ExportUtilityReadingsData $data): CsvExport
    {
        $query = UtilityReading::query()
            ->filtered([
                'organization_id' => (int) TenancyContext::organization()?->id,
                'unit_id' => $data->unitId,
                'utility' => UtilityType::tryFrom($data->utility) !== null ? $data->utility : null,
                'search' => $data->search,
                'sort' => $data->sort,
                'direction' => $data->direction,
            ])
            ->with(['unit:id,name', 'recorder:id,name']);

        if ($data->scope === 'page') {
            $query->forPage($data->page, 18);
        }

        $rows = $query->get()->map(fn (UtilityReading $reading): array => [
            $reading->id,
            $reading->unit?->name ?? '',
            $reading->utility->label(),
            $reading->reading,
            $reading->utility->unitOfMeasure(),
            $reading->consumption ?? '',
            $reading->read_on?->toDateString() ?? '',
            $reading->recorder?->name ?? '',
        ]);

        $suffix = $data->scope === 'page' ? '-page'.$data->page : '-all';

        return CsvExport::fromSelectedColumns(
            'utilities'.$suffix.'.csv',
            self::HEADERS,
            $rows,
            $data->columns,
        );
    }
}
