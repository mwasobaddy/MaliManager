<?php

namespace App\Actions\Staff;

use App\Models\Organization;
use App\Models\SubPermission;
use App\Models\SubRole;
use App\Models\User;
use App\Services\Service;
use App\Support\DefaultSubRoles;
use Illuminate\Support\Str;

/**
 * Creates an organization-scoped sub-role with a chosen set of
 * sub-permissions. Bundle slugs (landlord, caretaker, agent) are reserved:
 * the seeder syncs bundles by slug, so a custom role squatting one would
 * hijack the bundle on the next sync.
 */
class CreateSubRole extends Service
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Organization $organization, array $data): SubRole
    {
        $slug = Str::slug((string) ($data['name'] ?? ''));

        abort_if($slug === '', 422, 'The role needs a name.');
        abort_if(in_array($slug, array_keys(DefaultSubRoles::bundles()), true), 422, 'That name is reserved for a built-in role.');

        return $this->transaction(function () use ($actor, $organization, $data, $slug): SubRole {
            $role = $this->save(new SubRole([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'status' => 'active',
                'created_by' => $actor->id,
            ]));

            $role->subPermissions()->sync($this->permissionIds((array) ($data['permissions'] ?? [])));

            return $role->refresh();
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
