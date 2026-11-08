<?php

namespace App\Support;

use App\Models\User;

/**
 * The organizations a user belongs to — owned or staff — each with a link
 * into its property domain. Rendered as the sidebar's workspace section so
 * the way to work is persistent, not a card buried on one page.
 *
 * Central-domain staff see their way to work here instead of a bare guest
 * page; owners of property-less orgs land on their overview.
 */
final class WorkspaceLinks
{
    /**
     * @return array<int, array{organization_id: int, organization_name: string, role: string, url: string}>
     */
    public static function for(User $user, string $scheme): array
    {
        return $user->organizations()
            ->withPivot('is_owner')
            ->with('tenant.domains')
            ->orderBy('organizations.name')
            ->get(['organizations.id', 'organizations.name', 'organizations.tenant_id'])
            ->map(function ($organization) use ($scheme): array {
                $domain = $organization->tenant?->domains->value('domain');

                return [
                    'organization_id' => $organization->id,
                    'organization_name' => $organization->name,
                    'role' => $organization->pivot->is_owner ? 'owner' : 'staff',
                    'url' => $domain ? "{$scheme}://{$domain}/properties" : route('guest.home'),
                ];
            })
            ->all();
    }
}
