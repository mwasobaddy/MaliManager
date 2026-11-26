<?php

namespace App\Actions\Occupants;

use App\Data\Occupants\ImportOccupantsData;
use App\Services\OccupantService;
use App\Services\Service;
use App\Support\ImportResult;
use App\Support\TenancyContext;

/**
 * Bulk-add occupants to one property from an uploaded CSV:
 * First name, Last name, Email, Phone, National ID, Unit, Rent amount.
 *
 * Every row moves a tenant in immediately (active lease, first rent
 * recorded) through the same CreateOccupant path as the form, so an
 * import behaves exactly like typing each row by hand. Bad rows are
 * recorded and skipped rather than aborting the file.
 */
class ImportOccupants extends Service
{
    public function handle(ImportOccupantsData $data): ImportResult
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

            $columns = array_map(
                fn (string $column): string => strtolower(preg_replace('/[^a-z0-9]/i', '', $column)),
                $header,
            );

            $organization = TenancyContext::organization();
            $currency = $organization->settings['currency'] ?? 'KES';

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
                    $this->importRow($organization->id, $data, $record, $currency);
                    $imported++;
                } catch (\Throwable $e) {
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
    private function importRow(int $organizationId, ImportOccupantsData $data, array $record, string $currency): void
    {
        $firstName = (string) ($record['firstname'] ?? '');

        if ($firstName === '') {
            throw new \DomainException('First name is required.');
        }

        $unitName = (string) ($record['unit'] ?? '');

        if ($unitName === '') {
            throw new \DomainException('Unit is required.');
        }

        $unit = $data->property->units()->where('name', $unitName)->first();

        if ($unit === null) {
            throw new \DomainException("unknown unit \"{$unitName}\" in this property.");
        }

        if ($unit->status === 'occupied') {
            throw new \DomainException("unit \"{$unitName}\" is already occupied.");
        }

        $rent = $record['rentamount'] ?? null;

        if ($rent === null || $rent === '' || ! is_numeric($rent) || (float) $rent < 0) {
            throw new \DomainException('Rent amount must be a number of 0 or more.');
        }

        $email = (string) ($record['email'] ?? '');

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \DomainException("\"{$email}\" is not a valid email address.");
        }

        app(OccupantService::class)->create(
            $data->property->organization,
            $data->property,
            $data->actor,
            [
                'first_name' => $firstName,
                'last_name' => (string) ($record['lastname'] ?? '') ?: null,
                'email' => $email !== '' ? $email : null,
                'phone' => (string) ($record['phone'] ?? '') ?: null,
                'national_id' => (string) ($record['nationalid'] ?? '') ?: null,
                'status' => 'active',
                'unit_ids' => [$unit->id],
                'move_in_now' => true,
                'lease' => [
                    'rent_amount' => $rent,
                    'rent_frequency' => 'monthly',
                    'currency' => $currency,
                ],
            ],
        );
    }
}
