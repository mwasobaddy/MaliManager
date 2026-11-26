<?php

namespace App\Support;

use App\Models\User;

/**
 * Enforces an organization's staff two-factor policy at sign-in.
 *
 * Owners are exempt (they keep full access so they can always reach the
 * policy switch itself), and passwordless socialite-only accounts are
 * exempt until they set a password — without one they could never pass
 * the confirm-password gate in front of the 2FA setup page.
 *
 * @return array{url: string, message: string}|null
 */
class TwoFactorRequirement
{
    public static function for(User $user): ?array
    {
        $organization = $user->staffOrganization();

        if ($organization === null || ! $organization->requiresStaffTwoFactor()) {
            return null;
        }

        if (empty($user->password)) {
            return null;
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return null;
        }

        return [
            'url' => route('security.edit'),
            'message' => $organization->name.' requires two-factor authentication for staff. Please enable it to continue.',
        ];
    }
}
