<?php

namespace App\Enums;

/**
 * Fixed platform-wide catalog of sub-permissions that organization
 * sub-roles can grant. Keys are stored as "module.action".
 */
enum SubPermissionKey: string
{
    case PropertyManage = 'property.manage';
    case PropertyCreate = 'property.create';
    case PropertyEdit = 'property.edit';
    case PropertyDelete = 'property.delete';
    case PropertyDelegate = 'property.delegate';

    case UnitManage = 'unit.manage';
    case UnitCreate = 'unit.create';
    case UnitEdit = 'unit.edit';
    case UnitDelete = 'unit.delete';
    case UnitDelegate = 'unit.delegate';

    case LandParcelManage = 'land_parcel.manage';
    case LandParcelCreate = 'land_parcel.create';
    case LandParcelEdit = 'land_parcel.edit';
    case LandParcelDelete = 'land_parcel.delete';
    case LandParcelDelegate = 'land_parcel.delegate';

    case OccupantManage = 'occupant.manage';
    case OccupantCreate = 'occupant.create';
    case OccupantEdit = 'occupant.edit';
    case OccupantDelete = 'occupant.delete';

    case LeaseManage = 'lease.manage';
    case LeaseCreate = 'lease.create';
    case LeaseEdit = 'lease.edit';
    case LeaseDelete = 'lease.delete';
    case LeaseManageTemplates = 'lease.manage_templates';

    case PaymentManage = 'payment.manage';

    case ExpenseManage = 'expense.manage';

    case MaintenanceManage = 'maintenance.manage';
    case MaintenanceCreate = 'maintenance.create';
    case MaintenanceEdit = 'maintenance.edit';
    case MaintenanceDelete = 'maintenance.delete';
    case MaintenanceDelegate = 'maintenance.delegate';

    case StaffManage = 'staff.manage';
    case StaffCreate = 'staff.create';
    case StaffEdit = 'staff.edit';
    case StaffDelete = 'staff.delete';

    case AuditView = 'audit.view';

    /**
     * The module label for grouping in the UI.
     */
    public function module(): string
    {
        return match ($this) {
            self::PropertyManage, self::PropertyCreate, self::PropertyEdit, self::PropertyDelete, self::PropertyDelegate => 'property',
            self::UnitManage, self::UnitCreate, self::UnitEdit, self::UnitDelete, self::UnitDelegate => 'unit',
            self::LandParcelManage, self::LandParcelCreate, self::LandParcelEdit, self::LandParcelDelete, self::LandParcelDelegate => 'land_parcel',
            self::OccupantManage, self::OccupantCreate, self::OccupantEdit, self::OccupantDelete => 'occupant',
            self::LeaseManage, self::LeaseCreate, self::LeaseEdit, self::LeaseDelete, self::LeaseManageTemplates => 'lease',
            self::PaymentManage => 'payment',
            self::ExpenseManage => 'expense',
            self::MaintenanceManage, self::MaintenanceCreate, self::MaintenanceEdit, self::MaintenanceDelete, self::MaintenanceDelegate => 'maintenance',
            self::StaffManage, self::StaffCreate, self::StaffEdit, self::StaffDelete => 'staff',
            self::AuditView => 'audit',
        };
    }

    /**
     * Human-readable label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::PropertyManage => 'Manage properties',
            self::PropertyCreate => 'Create properties',
            self::PropertyEdit => 'Edit properties',
            self::PropertyDelete => 'Delete properties',
            self::PropertyDelegate => 'Delegate properties',
            self::UnitManage => 'Manage units',
            self::UnitCreate => 'Create units',
            self::UnitEdit => 'Edit units',
            self::UnitDelete => 'Delete units',
            self::UnitDelegate => 'Delegate units',
            self::LandParcelManage => 'Manage land parcels',
            self::LandParcelCreate => 'Create land parcels',
            self::LandParcelEdit => 'Edit land parcels',
            self::LandParcelDelete => 'Delete land parcels',
            self::LandParcelDelegate => 'Delegate land parcels',
            self::OccupantManage => 'Manage occupants',
            self::OccupantCreate => 'Create occupants',
            self::OccupantEdit => 'Edit occupants',
            self::OccupantDelete => 'Delete occupants',
            self::LeaseManage => 'Manage leases',
            self::LeaseCreate => 'Create leases',
            self::LeaseEdit => 'Edit leases',
            self::LeaseDelete => 'Delete leases',
            self::LeaseManageTemplates => 'Manage agreement templates',
            self::PaymentManage => 'Manage payments',
            self::ExpenseManage => 'Manage expenses',
            self::MaintenanceManage => 'Manage maintenance requests',
            self::MaintenanceCreate => 'Create maintenance requests',
            self::MaintenanceEdit => 'Edit maintenance requests',
            self::MaintenanceDelete => 'Delete maintenance requests',
            self::MaintenanceDelegate => 'Delegate maintenance requests',
            self::StaffManage => 'Manage staff',
            self::StaffCreate => 'Create staff',
            self::StaffEdit => 'Edit staff',
            self::StaffDelete => 'Delete staff',
            self::AuditView => 'View audit log',
        };
    }
}
