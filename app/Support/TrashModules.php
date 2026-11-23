<?php

namespace App\Support;

use App\Enums\SubPermissionKey;
use App\Models\Expense;
use App\Models\Inspection;
use App\Models\LandParcel;
use App\Models\LandParcelSection;
use App\Models\MaintenanceRequest;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlatformExpense;
use App\Models\PropertyType;
use App\Models\Role;
use App\Models\SubscriptionPayment;
use App\Models\Unit;
use App\Models\User;

/**
 * One registry for every soft-deleted module the trash can list.
 *
 * A module earns a row here when its model uses SoftDeletes AND a destroy
 * endpoint exists that puts rows into the trash. Excluded by design:
 * leases (status change, no SoftDeletes), agreement templates (hard
 * delete), roles (Spatie model, no SoftDeletes), properties (SoftDeletes
 * but no destroy route), staff memberships (hard-deleted pivots — staff
 * trash lists the underlying User, restore revives the login only).
 *
 * Tenant permission is the same gate as the destroy route, so trash can
 * never widen who may remove a row. Platform permission is the
 * `can:delete*` gate name used on the matching destroy route.
 */
final class TrashModules
{
    /**
     * @return array<string, array{model: class-string, label: string, singular: string, permission: string, scope: string}>
     */
    public static function tenant(): array
    {
        return [
            'units' => [
                'model' => Unit::class,
                'label' => 'Units',
                'singular' => 'unit',
                'permission' => SubPermissionKey::UnitDelete->value,
                'scope' => 'via_property',
            ],
            'occupants' => [
                'model' => Occupant::class,
                'label' => 'Occupants',
                'singular' => 'occupant',
                'permission' => SubPermissionKey::OccupantDelete->value,
                'scope' => 'direct',
            ],
            'inspections' => [
                'model' => Inspection::class,
                'label' => 'Inspections',
                'singular' => 'inspection',
                'permission' => SubPermissionKey::InspectionManage->value,
                'scope' => 'direct',
            ],
            'land-parcels' => [
                'model' => LandParcel::class,
                'label' => 'Land parcels',
                'singular' => 'land parcel',
                'permission' => SubPermissionKey::LandParcelDelete->value,
                'scope' => 'direct',
            ],
            'land-parcel-sections' => [
                'model' => LandParcelSection::class,
                'label' => 'Land parcel sections',
                'singular' => 'land parcel section',
                'permission' => SubPermissionKey::LandParcelManage->value,
                'scope' => 'via_parcel',
            ],
            'payments' => [
                'model' => Payment::class,
                'label' => 'Payments',
                'singular' => 'payment',
                'permission' => SubPermissionKey::PaymentManage->value,
                'scope' => 'direct',
            ],
            'expenses' => [
                'model' => Expense::class,
                'label' => 'Expenses',
                'singular' => 'expense',
                'permission' => SubPermissionKey::ExpenseManage->value,
                'scope' => 'direct',
            ],
            'maintenance' => [
                'model' => MaintenanceRequest::class,
                'label' => 'Maintenance',
                'singular' => 'maintenance request',
                'permission' => SubPermissionKey::MaintenanceDelete->value,
                'scope' => 'direct',
            ],
            'staff' => [
                'model' => User::class,
                'label' => 'Staff',
                'singular' => 'staff member',
                'permission' => SubPermissionKey::StaffDelete->value,
                'scope' => 'staff',
            ],
        ];
    }

    /**
     * @return array<string, array{model: class-string, label: string, singular: string, permission: string, scope: string}>
     */
    public static function platform(): array
    {
        return [
            'users' => [
                'model' => User::class,
                'label' => 'Users',
                'singular' => 'user',
                'permission' => 'deleteUsers',
                'scope' => 'central',
            ],
            'organizations' => [
                'model' => Organization::class,
                'label' => 'Organizations',
                'singular' => 'organization',
                'permission' => 'deleteOrganizations',
                'scope' => 'central',
            ],
            'plans' => [
                'model' => Plan::class,
                'label' => 'Plans',
                'singular' => 'plan',
                'permission' => 'deletePlans',
                'scope' => 'central',
            ],
            'subscription-payments' => [
                'model' => SubscriptionPayment::class,
                'label' => 'Subscription payments',
                'singular' => 'subscription payment',
                'permission' => 'deleteSubscriptionPayments',
                'scope' => 'central',
            ],
            'platform-expenses' => [
                'model' => PlatformExpense::class,
                'label' => 'Platform expenses',
                'singular' => 'platform expense',
                'permission' => 'deletePlatformExpenses',
                'scope' => 'central',
            ],
            'property-types' => [
                'model' => PropertyType::class,
                'label' => 'Property types',
                'singular' => 'property type',
                'permission' => 'deletePropertyTypes',
                'scope' => 'central',
            ],
            'roles' => [
                'model' => Role::class,
                'label' => 'Roles',
                'singular' => 'role',
                'permission' => 'deleteRoles',
                'scope' => 'central',
                // Roles are platform-global objects with no creator column,
                // so the delete gate is the whole fence — no created_by tier.
                'tier' => 'gate',
            ],
        ];
    }
}
