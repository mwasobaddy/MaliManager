<?php

namespace App\Actions\Users;

use App\Actions\Users\Concerns\UserHelpers;
use App\Models\User;
use App\Services\Service;
use Illuminate\Support\Facades\DB;

class UpdateUser extends Service
{
    use UserHelpers;

    public function handle(User $user, array $data, User $actor): User
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
}
