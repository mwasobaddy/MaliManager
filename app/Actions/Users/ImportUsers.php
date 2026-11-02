<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Data\Users\ImportUsersData;
use App\Services\Service;
use App\Services\UserService;
use App\Support\ImportResult;

/**
 * Create users from an uploaded CSV.
 *
 * Moved from `UserService::import()` unchanged: each row is validated and
 * created independently, and a failure is recorded and skipped so one bad row
 * cannot abort the file. Writes go through `UserService::create()` so a CSV user
 * is provisioned the same way as one added from the UI.
 */
class ImportUsers extends Service
{
    public function __construct(private UserService $users) {}

    public function handle(ImportUsersData $data): ImportResult
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

            // Normalise "First Name" / "first_name" / "FIRSTNAME" -> firstname
            $columns = array_map(
                fn (string $column): string => strtolower(preg_replace('/[^a-z0-9]/i', '', $column)),
                $header,
            );

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

                $firstName = (string) ($record['firstname'] ?? '');
                $email = (string) ($record['email'] ?? '');

                if ($firstName === '') {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Row '.$rowNumber.': First Name is required.'];

                    continue;
                }

                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Row '.$rowNumber.': a valid Email is required.'];

                    continue;
                }

                $status = strtolower((string) ($record['status'] ?? 'active')) ?: 'active';

                if (! in_array($status, ['active', 'inactive', 'suspended'], true)) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Row '.$rowNumber.': unknown status "'.$status.'".'];

                    continue;
                }

                $gender = strtolower((string) ($record['gender'] ?? '')) ?: null;

                if ($gender !== null && ! in_array($gender, ['male', 'female'], true)) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Row '.$rowNumber.': gender must be male or female.'];

                    continue;
                }

                $roles = array_values(array_filter(array_map(
                    fn (string $role): string => strtolower(trim($role)),
                    explode('|', (string) ($record['roles'] ?? '')),
                )));

                try {
                    $this->users->create([
                        'first_name' => $firstName,
                        'last_name' => (string) ($record['lastname'] ?? '') ?: null,
                        'email' => $email,
                        'phone' => (string) ($record['phone'] ?? '') ?: null,
                        'national_id' => (string) ($record['nationalid'] ?? '') ?: null,
                        'gender' => $gender,
                        'status' => $status,
                        'roles' => $roles,
                    ], $data->actor);

                    $imported++;
                } catch (\Throwable $e) {
                    // One bad row must not abort the rest of the file.
                    $errors[] = ['row' => $rowNumber, 'message' => 'Row '.$rowNumber.': '.$e->getMessage()];
                }
            }

            fclose($handle);

            return new ImportResult($imported, count($errors), $errors);
        });
    }
}
