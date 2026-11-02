<?php

namespace App\Actions\Users;

use App\Actions\Users\Concerns\UserHelpers;
use App\Models\User;
use App\Services\Service;

class DeleteUser extends Service
{
    use UserHelpers;

    public function handle(User $user, User $actor): void
    {
        if ($user->id === $actor->id) {
            abort(422, 'You cannot delete your own account.');
        }

        activity()->performedOn($user)->causedBy($actor)->log('Deleted user '.$user->email);

        $user->delete();
    }
}
