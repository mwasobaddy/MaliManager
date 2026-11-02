<?php

namespace App\Actions\Users\Concerns;

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait UserHelpers
{
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
     * Invalidate every active session for the user (database session driver),
     * so a suspended/inactivated account is kicked from all devices immediately
     * rather than only on its next request.
     */
    private function revokeSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
