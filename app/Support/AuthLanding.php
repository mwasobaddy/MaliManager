<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Decides where an authenticated user should land after login or
 * onboarding:
 *
 *  - un-onboarded users go to the onboarding wizard
 *  - organization owners go to their tenant subdomain (property
 *    picker each visit; a fresh org with no properties lands on the
 *    mandatory "add your first property" page)
 *  - occupants go to the central dashboard
 */
class AuthLanding
{
    public static function for(User $user, Request $request): string
    {
        if (! $user->isOnboarded()) {
            return route('onboarding.show');
        }

        $organization = $user->organizations()->wherePivot('is_owner', true)->first();

        if (! $organization) {
            return route('dashboard');
        }

        $domain = $organization->tenant?->domains()->first()?->domain;

        if (! $domain) {
            return route('dashboard');
        }

        $scheme = $request->secure() ? 'https' : 'http';

        $path = match (true) {
            $organization->properties()->doesntExist() => 'properties/create',
            $organization->properties()->count() === 1 => $organization->properties()->value('slug').'/dashboard',
            default => 'properties',
        };

        return "{$scheme}://{$domain}/{$path}";
    }

    /**
     * The tenant-subdomain URL for a property page.
     */
    public static function property(Organization $organization, string $slug, Request $request): string
    {
        $domain = $organization->tenant?->domains()->first()?->domain;

        if (! $domain) {
            return route('dashboard');
        }

        $scheme = $request->secure() ? 'https' : 'http';

        return "{$scheme}://{$domain}/{$slug}/dashboard";
    }
}
