<?php

namespace App\Services;

use App\Actions\Users\Concerns\UserHelpers;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\ImportUsers;
use App\Actions\Users\SetUserStatus;
use App\Actions\Users\UpdateUser;
use App\Data\Users\ImportUsersData;
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
    use UserHelpers;

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
        return app(UpdateUser::class)->handle($user, $data, $actor);
    }

    /**
     * Suspend or reactivate a user account.
     */
    public function setStatus(User $user, string $status, User $actor): User
    {
        return app(SetUserStatus::class)->handle($user, $status, $actor);
    }

    /**
     * Soft-delete a user account. The acting admin cannot delete themselves.
     */
    public function deleteUser(User $user, User $actor): void
    {
        app(DeleteUser::class)->handle($user, $actor);
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
        return app(ImportUsers::class)
            ->handle(new ImportUsersData($file, $actor))
            ->toArray();
    }
}
