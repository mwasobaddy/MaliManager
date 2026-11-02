<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Organizations\Concerns\OrganizationHelpers;
use App\Data\Organizations\ImportOrganizationsData;
use App\Models\Plan;
use App\Services\Service;
use App\Services\TenantService;
use App\Support\ImportResult;

/**
 * Create organizations from an uploaded CSV.
 *
 * Moved from `OrganizationManagementService::import()` without changing a
 * predicate or a message. Each row is provisioned independently and a failure is
 * recorded and skipped, so one bad row cannot abort the file; the surrounding
 * transaction therefore only rolls back work that raised past the per-row catch.
 */
class ImportOrganizations extends Service
{
    use OrganizationHelpers;

    public function __construct(private TenantService $tenants) {}

    public function handle(ImportOrganizationsData $data): ImportResult
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

            // Normalise "Owner Email" / "owner_email" / "OWNEREMAIL" -> owneremail
            $columns = array_map(
                fn ($column): string => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $column)),
                $header
            );

            $plans = Plan::pluck('id', 'name')->merge(Plan::pluck('id', 'slug'));
            $defaultPlan = Plan::find($data->planId) ?? Plan::orderBy('id')->first();

            $imported = 0;
            $errors = [];
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($row === [null] || implode('', array_map('trim', $row)) === '') {
                    continue; // blank line
                }

                $record = [];

                foreach ($columns as $index => $column) {
                    $record[$column] = trim((string) ($row[$index] ?? ''));
                }

                $name = $record['name'] ?? '';
                $ownerEmail = $record['owneremail'] ?? '';

                if ($name === '') {
                    $errors[] = ['row' => $rowNumber, 'message' => "Row {$rowNumber}: Name is required."];

                    continue;
                }

                if (! filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = ['row' => $rowNumber, 'message' => "Row {$rowNumber} ({$name}): Owner Email must be a valid email address."];

                    continue;
                }

                $planName = $record['plan'] ?? '';

                $plan = ($planName !== '' ? $plans->get($planName) : null)
                    ? Plan::find($plans->get($planName))
                    : $defaultPlan;

                if (! $plan) {
                    $errors[] = ['row' => $rowNumber, 'message' => "Row {$rowNumber} ({$name}): unknown plan \"{$planName}\"."];

                    continue;
                }

                try {
                    $owner = $this->findOrCreateOwner($ownerEmail);

                    $this->tenants->createOrganization(
                        $owner,
                        $name,
                        $plan,
                        ($record['email'] ?? '') ?: null,
                        ($record['phone'] ?? '') ?: null,
                    );

                    $imported++;
                } catch (\Throwable $e) {
                    // One bad row must not abort the rest of the file.
                    $errors[] = ['row' => $rowNumber, 'message' => "Row {$rowNumber} ({$name}): ".$e->getMessage()];
                }
            }

            fclose($handle);

            if ($data->actor && $imported > 0) {
                activity()
                    ->causedBy($data->actor)
                    ->event('organizations.imported')
                    ->log("Imported {$imported} organization(s) from CSV");
            }

            return new ImportResult($imported, count($errors), $errors);
        });
    }
}
