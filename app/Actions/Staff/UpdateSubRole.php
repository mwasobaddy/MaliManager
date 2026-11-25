<?php

namespace App\Actions\Staff;

use App\Models\Organization;
use App\Models\SubPermission;
use App\Models\SubRole;
use App\Models\User;
use App\Services\Service;

/**
 * Renames a sub-role and re-points its permission set. The landlord bundle
 * is locked: it always holds every permission by definition, so there is
 * nothing to edit and no way to lock the organization out of itself.
 */
class UpdateSubRole extends Service
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Organization $organization, SubRole $subRole, array $data): SubRole
    {
        abort_unless($subRole->organization_id === $organization->id, 404);
        abort_if($subRole->slug === 'landlord', 422, 'The landlord role always holds every permission.');

        return $this->transaction(function () use ($subRole, $data): SubRole {
            $subRole->fill([
                'name' => $data['name'] ?? $subRole->name,
                'description' => $data['description'] ?? $subRole->description,
            ]);
            $this->save($subRole);

            if (array_key_exists('permissions', $data)) {
                $subRole->subPermissions()->sync($this->permissionIds((array) $data['permissions']));
            }

            return $subRole->refresh();
        });
    }

    /**
     * @param  list<string>  $keys
     * @return list<int>
     */
    private function permissionIds(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        return SubPermission::query()->whereIn('key', array_values(array_unique($keys)))->pluck('id')->all();
    }
}
