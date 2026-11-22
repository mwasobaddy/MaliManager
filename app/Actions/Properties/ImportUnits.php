<?php

namespace App\Actions\Properties;

use App\Data\Units\ImportUnitsData;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Service;
use App\Support\ImportResult;
use App\Support\TenancyContext;
use Illuminate\Support\Collection;

/**
 * Create or update units from an uploaded CSV shaped like the export:
 * ID, Name, Type, Billing frequency, Rent amount, Status, Property,
 * Photos, Created at.
 *
 * An ID that belongs to this organization updates that unit in place;
 * without one, the row creates under the named property. The Photos
 * column is read-only metadata from exports — image files cannot travel
 * in a CSV, so it is parsed and ignored with no row error. Status accepts
 * vacant and maintenance only: occupied arrives through lease assignment,
 * never through a spreadsheet.
 */
class ImportUnits extends Service
{
    public function handle(ImportUnitsData $data): ImportResult
    {
        return $this->transaction(function () use ($data): ImportResult {
            $handle = fopen($data->file->getRealPath(), 'r');

            if ($handle === false) {
                return new ImportResult(0, 1, [['row' => 0, 'message' => 'The uploaded file could not be read.']]);
            }

            $header = fgetcsv($handle);

            if ($header === false || $header === [null]) {
                fclose($handle);

                return new ImportResult(0, 1, [['row' => 0, 'message' => 'The CSV has no header row.']]);
            }

            // Normalise "Billing Frequency" / "billingfrequency" -> billingfrequency
            $columns = array_map(
                fn (string $column): string => strtolower(preg_replace('/[^a-z0-9]/i', '', $column)),
                $header,
            );

            $organization = TenancyContext::organization();
            $properties = $organization->properties()->orderBy('name')->get(['id', 'name']);

            $errors = [];
            $imported = 0;
            $rowNumber = 1;

            while (($values = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($values === [null] || $values === []) {
                    continue;
                }

                $record = array_combine($columns, array_pad($values, count($columns), null));
                $record = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $record);

                try {
                    $this->importRow($properties, $data->actor->id, $record);
                    $imported++;
                } catch (\DomainException $e) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Row '.$rowNumber.': '.$e->getMessage()];
                }
            }

            fclose($handle);

            return new ImportResult($imported, count($errors), $errors);
        });
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRow(Collection $properties, int $actorId, array $record): void
    {
        $name = (string) ($record['name'] ?? '');

        if ($name === '') {
            throw new \DomainException('Name is required.');
        }

        $frequency = strtolower((string) ($record['billingfrequency'] ?? 'monthly')) ?: 'monthly';

        if (! in_array($frequency, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            throw new \DomainException("unknown billing frequency \"{$record['billingfrequency']}\".");
        }

        $amount = ($record['rentamount'] ?? null);

        if ($amount !== null && $amount !== '' && (! is_numeric($amount) || (float) $amount < 0)) {
            throw new \DomainException('Rent amount must be a number of 0 or more.');
        }

        $status = strtolower((string) ($record['status'] ?? 'vacant')) ?: 'vacant';

        if (! in_array($status, ['vacant', 'maintenance'], true)) {
            throw new \DomainException('status must be vacant or maintenance — occupied arrives through lease assignment.');
        }

        $propertyName = (string) ($record['property'] ?? '');

        if ($propertyName === '') {
            throw new \DomainException('Property is required.');
        }

        $property = $properties->first(
            fn (Property $property): bool => strtolower($property->name) === strtolower($propertyName),
        );

        if ($property === null) {
            throw new \DomainException("unknown property \"{$propertyName}\".");
        }

        $unitId = (int) ($record['id'] ?? 0);
        $unit = $unitId > 0
            ? Unit::query()
                ->where('id', $unitId)
                ->where('property_id', $property->id)
                ->first()
            : null;

        if ($unitId > 0 && $unit === null) {
            throw new \DomainException("no unit with ID {$unitId} in \"{$propertyName}\".");
        }

        $attributes = [
            'name' => $name,
            'type' => ((string) ($record['type'] ?? '') ?: null),
            'rent_frequency' => $frequency,
            'rent_amount' => ($amount === null || $amount === '') ? null : $amount,
            'status' => $status,
        ];

        if ($unit !== null) {
            if ($status !== $unit->status && $this->hasActiveLease($unit)) {
                throw new \DomainException('unit is occupied — change status through the move-out flow, not the import.');
            }

            $unit->update($attributes);

            return;
        }

        $property->units()->create($attributes + ['created_by' => $actorId]);
    }

    private function hasActiveLease(Unit $unit): bool
    {
        return Lease::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'active')
            ->exists();
    }
}
