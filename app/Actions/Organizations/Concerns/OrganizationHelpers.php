<?php

namespace App\Actions\Organizations\Concerns;

use App\Enums\PlatformRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait OrganizationHelpers
{
    /**
     * Find the owner account by email, or create a ready-to-use one. Onboards
     * them immediately so they are not forced through onboarding before they
     * can reach their new organization.
     */
    private function findOrCreateOwner(string $email): User
    {
        $owner = User::where('email', $email)->first();

        if ($owner) {
            return $owner;
        }

        $owner = new User(['email' => $email]);
        $owner->password = Hash::make(Str::random(40));
        $owner->name = Str::before($email, '@');
        $owner->onboarded_at = now();
        $owner->status = 'active';
        $owner->email_verified_at = now();
        $owner->save();

        $owner->assignRole(PlatformRole::OrganizationOwner->value);

        return $owner;
    }
}
