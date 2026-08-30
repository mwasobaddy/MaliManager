<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;
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
}
