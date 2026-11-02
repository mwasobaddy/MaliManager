<?php

declare(strict_types=1);

namespace App\Actions\PropertyTypes;

use App\Data\PropertyTypes\ExportPropertyTypesData;
use App\Models\PropertyType;
use App\Services\Service;
use App\Support\CsvExport;

/**
 * The property-type catalogue CSV.
 *
 * Export rather than import is the useful direction here: the catalogue is
 * seeded per organization and an owner adds a type by hand in seconds. Headers
 * and column order are unchanged.
 */
class ExportPropertyTypes extends Service
{
    public function handle(ExportPropertyTypesData $data): CsvExport
    {
        $query = PropertyType::query()
            ->filtered([
                'search' => $data->search,
                'category' => $data->category,
            ])
            ->withCount('properties')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($data->scope === 'page') {
            $query->forPage($data->page, 20);
        }

        $rows = $query->get()->map(fn (PropertyType $type): array => [
            $type->name,
            $type->category->value,
            $type->properties_count,
            $type->description ?? '',
        ]);

        return CsvExport::fromRows(
            'property-types.csv',
            ['Name', 'Category', 'Properties in use', 'Description'],
            $rows,
        );
    }
}
