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
                ],
            ],
        ];
    }
}
