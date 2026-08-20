<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Decides where an authenticated user should land after login or
 * onboarding:
 *
 *  - un-onboarded users go to the onboarding wizard
 *  - organization owners go to their tenant subdomain (property
 *    picker each visit; a fresh org with no properties lands on the
 *    mandatory "add your first property" page)
 *  - staff (non-owner members with a sub-role) go to their org's
 *    subdomain: straight to their single assigned property, or the
 *    property picker when they manage several, or the picker with a
 *    toast when nothing is assigned yet
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

        if ($organization) {
            return self::organizationLanding($organization, $request);
        }

        $staffOrganization = $user->staffOrganization();

        if ($staffOrganization) {
            return self::staffLanding($staffOrganization, $user, $request);
        }

        return route('dashboard');
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

    private static function organizationLanding(Organization $organization, Request $request): string
    {
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

    private static function staffLanding(Organization $organization, User $user, Request $request): string
    {
        $domain = $organization->tenant?->domains()->first()?->domain;

        if (! $domain) {
            return route('dashboard');
        }

        $properties = app(StaffService::class)->managedProperties($organization, $user);

        if ($properties->isEmpty()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'No property has been assigned to you yet. Please contact your administrator.',
            ]);

            $path = 'properties';
        } elseif ($properties->count() === 1) {
            $path = $properties->first()->slug.'/dashboard';
        } else {
            $path = 'properties';
        }

        $scheme = $request->secure() ? 'https' : 'http';

        return "{$scheme}://{$domain}/{$path}";
    }
}
