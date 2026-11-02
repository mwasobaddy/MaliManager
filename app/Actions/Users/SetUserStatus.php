<?php

namespace App\Actions\Users;

use App\Actions\Users\Concerns\UserHelpers;
use App\Models\User;
use App\Services\Service;

class SetUserStatus extends Service
{
    use UserHelpers;

    public function handle(User $user, string $status, User $actor): User
    {
        $user->fill(['status' => $status])->save();

        if ($status !== 'active') {
            $this->revokeSessions($user);
        }

        activity()->performedOn($user)->causedBy($actor)->log(ucfirst($status).' user '.$user->email);

        return $user;
    }
}
