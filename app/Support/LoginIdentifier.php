<?php

namespace App\Support;

use App\Models\User;

/**
 * Resolves a login identifier — email address or phone number in any of
 * the ways people type it — to the matching account.
 *
 * Numbers are stored canonical since the phone mutators landed, so the
 * exact match handles current rows. Older rows typed with spaces or
 * dashes fall through to a stripped comparison instead of locking
 * their owners out.
 */
class LoginIdentifier
{
    public static function findUser(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if (filter_var(strtolower($identifier), FILTER_VALIDATE_EMAIL) !== false) {
            return User::where('email', strtolower($identifier))->first();
        }

        $canonical = PhoneNumber::normalize($identifier);

        if ($canonical === null) {
            return null;
        }

        if ($user = User::where('phone', $canonical)->first()) {
            return $user;
        }

        $digits = preg_replace('/\D/', '', $canonical);

        if (! is_string($digits) || strlen($digits) < 4) {
            return null;
        }

        // Legacy rows typed with spaces or dashes: narrow by the trailing
        // digits (almost always contiguous), then compare exactly in PHP.
        return User::query()
            ->whereNotNull('phone')
            ->where('phone', 'like', '%'.substr($digits, -4))
            ->get(['id', 'phone'])
            ->first(fn (User $user): bool => PhoneNumber::normalize((string) $user->phone) === $canonical);
    }
}
