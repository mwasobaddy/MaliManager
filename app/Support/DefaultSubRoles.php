<?php

namespace App\Support;

use App\Enums\SubPermissionKey;
use App\Models\Organization;
use App\Models\SubPermission;

/**
 * Default organization sub-roles and the permission bundles each one
 * ships with when an organization is created. Owners can edit these.
 */
class DefaultSubRoles
{
    public const LANDLORD = 'landlord';

    public const CARETAKER = 'caretaker';

    public const AGENT = 'agent';

    /**
     * Create the default sub-roles for a newly created organization.
     */
    public static function createFor(Organization $organization, ?int $createdBy = null): void
    {
        foreach (self::bundles() as $slug => $role) {
            $subRole = $organization->subRoles()->create([
                'name' => $role['name'],
                'slug' => $slug,
                'description' => $role['description'],
                'created_by' => $createdBy,
            ]);

            $keys = array_map(fn (SubPermissionKey $key): string => $key->value, $role['permissions']);
            $subRole->subPermissions()->sync(
                SubPermission::whereIn('key', $keys)->pluck('id')
            );
        }
    }

    /**
     * Add any bundle permissions an existing organization's sub-roles are
     * missing, without removing anything an owner has since customised.
     *
     * Bundles() only applies at creation time, so a permission added to a
     * bundle later (e.g. PropertyManage for caretakers) would otherwise never
     * reach organizations that already exist. Additive by design:
     * `syncWithoutDetaching` never detaches, so an owner who deliberately
     * trimmed a role keeps their version.
     *
     * @return int Number of permission rows added.
     */
    public static function syncBundlesFor(Organization $organization): int
    {
        $added = 0;

        foreach (self::bundles() as $slug => $role) {
            $subRole = $organization->subRoles()->where('slug', $slug)->first();

            if (! $subRole) {
                continue;
            }

            $keys = array_map(fn (SubPermissionKey $key): string => $key->value, $role['permissions']);

            $missing = SubPermission::whereIn('key', $keys)
                ->whereNotIn('id', $subRole->subPermissions()->pluck('sub_permission_id'))
                ->pluck('id');

            if ($missing->isNotEmpty()) {
                $subRole->subPermissions()->syncWithoutDetaching($missing);
                $added += $missing->count();
            }
        }

        return $added;
    }

    /**
     * The default sub-role definitions keyed by slug.
     *
     * @return array<string, array{name: string, description: string, permissions: list<SubPermissionKey>}>
     */
    public static function bundles(): array
    {
        return [
            self::LANDLORD => [
                'name' => 'Landlord',
                'description' => 'Full management access to the organization.',
                'permissions' => SubPermissionKey::cases(),
            ],
            self::CARETAKER => [
                'name' => 'Caretaker',
                'description' => 'Day-to-day operations: occupants, units, land parcels, leases, maintenance, and staff.',
                'permissions' => [
                    // Gates only GET /properties (the picker) — see the note on
                    // the bundle(). Without it a caretaker is redirected away
                    // from /properties, which is exactly where the staff
                    // landing flow (AuthLanding) sends them. It grants no write
                    // access: create is property.create and create/store are
                    // additionally owner-only in PropertyController.
                    SubPermissionKey::PropertyManage,
                    SubPermissionKey::OccupantManage,
                    SubPermissionKey::OccupantCreate,
                    SubPermissionKey::OccupantEdit,
                    SubPermissionKey::UnitManage,
                    SubPermissionKey::UnitEdit,
                    SubPermissionKey::LandParcelManage,
                    SubPermissionKey::LandParcelCreate,
                    SubPermissionKey::LandParcelEdit,
                    SubPermissionKey::LeaseManage,
                    SubPermissionKey::LeaseEdit,
                    SubPermissionKey::MaintenanceManage,
                    SubPermissionKey::MaintenanceCreate,
                    SubPermissionKey::MaintenanceEdit,
                    SubPermissionKey::StaffManage,
                    SubPermissionKey::StaffCreate,
                    SubPermissionKey::StaffEdit,
                    SubPermissionKey::AiUse,
                ],
            ],
            self::AGENT => [
                'name' => 'Agent',
                'description' => 'Property listing and viewing access, no financial operations.',
                'permissions' => [
                    SubPermissionKey::PropertyManage,
                    SubPermissionKey::PropertyEdit,
                    SubPermissionKey::UnitManage,
                    SubPermissionKey::UnitEdit,
                    SubPermissionKey::OccupantManage,
                    SubPermissionKey::LandParcelManage,
                    SubPermissionKey::LeaseManage,
                    SubPermissionKey::AiUse,
                ],
            ],
        ];
    }
}
