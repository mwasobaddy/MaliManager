<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Central user management. Creates and maintains the global Person
 * identity together with its user account(s). Persons are deduped by
 * email so a searcher, occupant, or staff member created elsewhere is
 * linked rather than duplicated.
 */
class UserService extends Service
{
    /**
     * Create (or link) a person and their user account.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $person = $this->findOrCreatePerson($data, $actor);
            $user = $this->findOrCreateUser($data, $person);

            if (! empty($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            activity()->performedOn($user)->causedBy($actor)->log('Created user '.$user->email);

            return $user;
        });
    }

    /**
     * Update a user account and its linked person.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            $user->fill([
                'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: $user->name,
                'email' => $data['email'] ?? $user->email,
                'phone' => $data['phone'] ?? $user->phone,
                'status' => $data['status'] ?? $user->status,
            ])->save();

            if ($user->person) {
                $user->person->fill([
                    'first_name' => $data['first_name'] ?? $user->person->first_name,
                    'last_name' => $data['last_name'] ?? $user->person->last_name,
                    'email' => $data['email'] ?? $user->person->email,
                    'phone' => $data['phone'] ?? $user->person->phone,
                    'national_id' => $data['national_id'] ?? $user->person->national_id,
                    'gender' => $data['gender'] ?? $user->person->gender,
                    'status' => $data['status'] ?? $user->person->status,
                ])->save();
            }

            if ($user->status !== 'active') {
                $this->revokeSessions($user);
            }

            if (array_key_exists('roles', $data)) {
                $user->syncRoles($data['roles'] ?? []);
            }

            activity()->performedOn($user)->causedBy($actor)->log('Updated user '.$user->email);

            return $user->refresh();
        });
    }

    /**
     * Suspend or reactivate a user account.
     */
    public function setStatus(User $user, string $status, User $actor): User
    {
        $user->fill(['status' => $status])->save();

        if ($status !== 'active') {
            $this->revokeSessions($user);
        }

        activity()->performedOn($user)->causedBy($actor)->log(ucfirst($status).' user '.$user->email);

        return $user;
    }

    /**
     * Soft-delete a user account. The acting admin cannot delete themselves.
     */
    public function deleteUser(User $user, User $actor): void
    {
        if ($user->id === $actor->id) {
            abort(422, 'You cannot delete your own account.');
        }

        activity()->performedOn($user)->causedBy($actor)->log('Deleted user '.$user->email);

        $user->delete();
    }

    /**
     * Invalidate every active session for the user (database session driver),
     * so a suspended/inactivated account is kicked from all devices immediately
     * rather than only on its next request.
     */
    private function revokeSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    /**
     * Find by email or create the global Person record.
     *
     * @param  array<string, mixed>  $data
     */
    private function findOrCreatePerson(array $data, User $actor): Person
    {
        $person = Person::where('email', $data['email'])->first();

        if ($person) {
            $person->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? $person->phone,
                'national_id' => $data['national_id'] ?? $person->national_id,
                'gender' => $data['gender'] ?? $person->gender,
            ])->save();

            return $person;
        }

        return $this->save(new Person([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'gender' => $data['gender'] ?? null,
            'status' => 'active',
            'created_by' => $actor->id,
        ]));
    }

    /**
     * Find by email or create the user account for a person.
     *
     * @param  array<string, mixed>  $data
     */
    private function findOrCreateUser(array $data, Person $person): User
    {
        $user = User::withTrashed()->where('email', $data['email'])->first();

        if ($user) {
            if ($user->trashed()) {
                $user->restore();
            }

            $user->fill([
                'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: $user->name,
                'phone' => $data['phone'] ?? $user->phone,
                'person_id' => $user->person_id ?? $person->id,
            ])->save();

            return $user;
        }

        return $this->save(new User([
            'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'person_id' => $person->id,
            'status' => $data['status'] ?? 'active',
            'onboarded_at' => now(),
        ]));
    }

    /**
     * Create users from an uploaded CSV.
     *
     * Rows are processed independently so one bad row cannot abort the file —
     * the same contract as the organizations import. Each row goes through
     * `create()`, so an imported user is identical to a hand-created one:
     * the person is found-or-created by email, the account is linked, and
     * roles are synced.
     *
     * @return array{imported: int, failed: int, errors: array<int, array{row: int, message: string}>}
     */
    public function import(UploadedFile $file, User $actor): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return ['imported' => 0, 'failed' => 1, 'errors' => [['row' => 0, 'message' => 'The uploaded file could not be read.']]];
        }

        $header = fgetcsv($handle);

        if ($header === false || $header === [null]) {
            fclose($handle);

            return ['imported' => 0, 'failed' => 1, 'errors' => [['row' => 0, 'message' => 'The CSV has no header row.']]];
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
                $this->create([
                    'first_name' => $firstName,
                    'last_name' => (string) ($record['lastname'] ?? '') ?: null,
                    'email' => $email,
                    'phone' => (string) ($record['phone'] ?? '') ?: null,
                    'national_id' => (string) ($record['nationalid'] ?? '') ?: null,
                    'gender' => $gender,
                    'status' => $status,
                    'roles' => $roles,
                ], $actor);

                $imported++;
            } catch (\Throwable $e) {
                // One bad row must not abort the rest of the file.
                $errors[] = ['row' => $rowNumber, 'message' => 'Row '.$rowNumber.': '.$e->getMessage()];
            }
        }

        fclose($handle);

        return ['imported' => $imported, 'failed' => count($errors), 'errors' => $errors];
    }
}
