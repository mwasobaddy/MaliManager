<?php

declare(strict_types=1);

namespace App\Actions\SubscriptionPayments;

use App\Data\SubscriptionPayments\ImportSubscriptionPaymentsData;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Services\Service;
use App\Support\CsvReader;
use App\Support\ImportResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Create subscription payments from an uploaded CSV.
 *
 * Subscription history usually arrives as a spreadsheet exported from another
 * system, so this is the import that matters most. Rows are independent: a bad
 * row is recorded and skipped. An unrecognised organization or plan name leaves
 * the payment unlinked rather than failing the row — the money still arrived.
 */
class ImportSubscriptionPayments extends Service
{
    public function __construct(private CsvReader $csv) {}

    public function handle(ImportSubscriptionPaymentsData $data): ImportResult
    {
        return $this->transaction(function () use ($data): ImportResult {
            $parsed = $this->csv->rows($data->file);
            $errors = [];
            $imported = 0;

            // Keyed by lower-cased name so a spreadsheet's stray capitalisation
            // still matches. `pluck('id', 'name')` would be case-sensitive and
            // `firstWhere()` passes (value, key), which is easy to get backwards.
            $organizations = $this->nameLookup(Organization::query()->get(['id', 'name']));
            $plans = $this->nameLookup(Plan::query()->get(['id', 'name']));

            foreach ($parsed['rows'] as $row) {
                $rowData = $row['data'];
                $amount = $rowData['amount'] ?? '';
                $receivedOn = $rowData['receivedon'] ?? '';

                if ($amount === '' || ! is_numeric($amount) || (float) $amount < 0) {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: Amount must be a number of zero or more."];

                    continue;
                }

                if ($receivedOn === '') {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: Received On is required."];

                    continue;
                }

                SubscriptionPayment::create([
                    'organization_id' => $this->resolveByName($organizations, $rowData['organization'] ?? ''),
                    'plan_id' => $this->resolveByName($plans, $rowData['plan'] ?? ''),
                    'amount' => $amount,
                    'currency' => strtoupper($rowData['currency'] ?? '') ?: 'KES',
                    'received_on' => $receivedOn,
                    'method' => ($rowData['method'] ?? '') ?: null,
                    'reference' => ($rowData['reference'] ?? '') ?: null,
                    'notes' => ($rowData['notes'] ?? '') ?: null,
                    'created_by' => $data->actor?->id,
                ]);

                $imported++;
            }

            if (! $parsed['ok']) {
                $errors = $parsed['rows'];
            }

            return new ImportResult($imported, count($errors), $errors);
        });
    }

    /**
     * @return array<string, int>
     */
    private function nameLookup(Collection $models): array
    {
        $lookup = [];

        foreach ($models as $model) {
            $lookup[Str::lower(trim($model->name))] = $model->id;
        }

        return $lookup;
    }

    /**
     * Match a CSV cell against a lower-cased `name => id` lookup.
     *
     * @param  array<string, int>  $lookup
     */
    private function resolveByName(array $lookup, string $name): ?int
    {
        if (trim($name) === '') {
            return null;
        }

        return $lookup[Str::lower(trim($name))] ?? null;
    }
}
