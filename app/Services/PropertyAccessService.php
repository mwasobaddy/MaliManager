<?php

namespace App\Services;

use App\Models\Delegation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves the organizations a user belongs to together with the
 * properties they can actually access inside each one. Owners see every
 * property of the organization; staff only see the properties delegated to
 * them through a {@see Delegation}.
 */
class PropertyAccessService extends Service
{
    /**
     * @return array<int, array{id: int, name: string, slug: string, domain: string|null, is_owner: bool, properties: array<int, array{id: int, name: string, slug: string, city: string|null, status: string, units_count: int}>}>
     */
    public function organizationsWithProperties(User $user): array
    {
        // Organizations with their tenant domains in two queries. This runs on
        // every Inertia request (shared auth data), so it must stay a bounded
        // set of queries regardless of how many organizations the user has.
        $organizations = $user->organizations()
            ->with('tenant.domains')
            ->get();

        if ($organizations->isEmpty()) {
            return [];
        }

        $memberships = OrganizationUser::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('organization_id');

        $ownerOrgIds = $organizations
            ->filter(fn (Organization $organization) => (bool) $organization->pivot->is_owner)
            ->pluck('id')
            ->all();

        // All properties of the user's owned organizations in one query.
        $ownerProperties = collect();
        if ($ownerOrgIds !== []) {
            $ownerProperties = Property::query()
                ->withCount('units')
                ->whereIn('organization_id', $ownerOrgIds)
                ->get()
                ->groupBy('organization_id');
        }

        // Delegated properties for staff memberships in at most two queries.
        $staffMembershipIds = $memberships
            ->filter(fn (OrganizationUser $membership) => ! (bool) ($organizations->find($membership->organization_id)->pivot->is_owner ?? false))
            ->pluck('id');

        $delegatedByMembership = collect();
        if ($staffMembershipIds->isNotEmpty()) {
            $delegatedByMembership = Delegation::query()
                ->where('delegatable_type', Property::class)
                ->whereIn('organization_user_id', $staffMembershipIds)
                ->get(['organization_user_id', 'delegatable_id'])
                ->groupBy('organization_user_id');
        }

        $staffPropertyIds = $delegatedByMembership
            ->flatten(1)
            ->pluck('delegatable_id')
            ->unique()
            ->all();

        $staffProperties = collect();
        if ($staffPropertyIds !== []) {
            $staffProperties = Property::query()
                ->withCount('units')
                ->whereIn('id', $staffPropertyIds)
                ->get()
                ->keyBy('id');
        }

        return $organizations
            ->map(function (Organization $organization) use ($memberships, $ownerProperties, $delegatedByMembership, $staffProperties): array {
                $isOwner = (bool) $organization->pivot->is_owner;

                if ($isOwner) {
                    $properties = $ownerProperties->get($organization->id, collect());
                } else {
                    $membership = $memberships->get($organization->id);
                    $ids = $membership
                        ? $delegatedByMembership->get($membership->id, collect())->pluck('delegatable_id')->all()
                        : [];

                    $properties = collect($ids)
                        ->map(fn (int $id) => $staffProperties->get($id))
                        ->filter()
                        ->values();
                }

                return [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'domain' => $organization->tenant?->domains->first()?->domain,
                    'is_owner' => $isOwner,
                    'properties' => $properties->map(function (Property $property): array {
                        return [
                            'id' => $property->id,
                            'name' => $property->name,
                            'slug' => $property->slug,
                            'city' => $property->city,
                            'status' => $property->status,
                            'units_count' => (int) ($property->units_count ?? 0),
                        ];
                    })->all(),
                ];
            })
            ->all();
    }

    /**
     * Every property the user can access across all their organizations.
     */
    public function allProperties(User $user): Collection
    {
        return collect($this->organizationsWithProperties($user))
            ->flatMap(fn (array $organization): array => $organization['properties']);
    }
}
