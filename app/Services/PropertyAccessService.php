<?php

namespace App\Services;

use App\Models\Delegation;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

/**
 * Resolves the organizations a user belongs to together with the
 * properties they can actually access inside each one. Owners see every
 * property of the organization; staff only see the properties delegated to
 * them through a {@see Delegation}.
 */
class PropertyAccessService extends Service
{
    /**
     * @return array<int, array{id: int, name: string, slug: string, is_owner: bool, properties: array<int, array{id: int, name: string, slug: string, city: string|null, status: string, units_count: int}>}>
     */
    public function organizationsWithProperties(User $user): array
    {
        return $user->organizations()
            ->get()
            ->map(function (Organization $organization) use ($user): array {
                $isOwner = (bool) $organization->pivot->is_owner;

                $properties = $isOwner
                    ? $organization->properties()->withCount('units')->get()
                    : App::make(StaffService::class)->managedProperties($organization, $user);

                return [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'domain' => $organization->tenant?->domains()->first()?->domain,
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
