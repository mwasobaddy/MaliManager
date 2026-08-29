<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Platform-wide (central) permissions that can be granted to a role,
 * independent of the organization-scoped SubPermission system.
 * Each case maps to a Gate defined in App\Providers\AuthServiceProvider.
 */
enum PlatformPermissionKey: string
{
    case ViewAudit = 'view audit';
    case ExportAudit = 'export audit';
    case ManageRoles = 'manage roles';
    case AccessAdminDashboard = 'access admin dashboard';
    case ViewAdvancedMetrics = 'view advanced metrics';
    case ViewOrgMetrics = 'view organization metrics';
    case ViewSearcherMetrics = 'view searcher metrics';
    case ViewOccupantMetrics = 'view occupant metrics';

    case UserManage = 'user.manage';
    case UserCreate = 'user.create';
    case UserEdit = 'user.edit';
    case UserDelete = 'user.delete';
    case UserExport = 'user.export';
    case UserImport = 'user.import';

    case OrganizationManage = 'organization.manage';
    case OrganizationCreate = 'organization.create';
    case OrganizationEdit = 'organization.edit';
    case OrganizationDelete = 'organization.delete';
    case OrganizationExport = 'organization.export';
    case OrganizationImport = 'organization.import';

    case PlanManage = 'plan.manage';
    case PlanCreate = 'plan.create';
    case PlanEdit = 'plan.edit';
    case PlanDelete = 'plan.delete';

    case AiUse = 'ai.use';

    public function label(): string
    {
        return match ($this) {
            self::ViewAudit => 'View audit log',
            self::ExportAudit => 'Export audit log',
            self::ManageRoles => 'Manage roles',
            self::AccessAdminDashboard => 'Access admin dashboard',
            self::ViewAdvancedMetrics => 'View advanced metrics',
            self::ViewOrgMetrics => 'View organization metrics',
            self::ViewSearcherMetrics => 'View searcher metrics',
            self::ViewOccupantMetrics => 'View occupant metrics',
            self::UserManage => 'Manage users',
            self::UserCreate => 'Create users',
            self::UserEdit => 'Edit users',
            self::UserDelete => 'Delete users',
            self::UserExport => 'Export users',
            self::UserImport => 'Import users',
            self::OrganizationManage => 'Manage organizations',
            self::OrganizationCreate => 'Create organizations',
            self::OrganizationEdit => 'Edit organizations',
            self::OrganizationDelete => 'Delete organizations',
            self::OrganizationExport => 'Export organizations',
            self::OrganizationImport => 'Import organizations',
            self::PlanManage => 'Manage plans',
            self::PlanCreate => 'Create plans',
            self::PlanEdit => 'Edit plans',
            self::PlanDelete => 'Delete plans',
            self::AiUse => 'Use the AI assistant',
        };
    }

    public function gate(): string
    {
        return match ($this) {
            self::ViewAudit => 'viewAnyAudit',
            self::ExportAudit => 'exportAudit',
            self::ManageRoles => 'manageRoles',
            self::AccessAdminDashboard => 'accessAdminDashboard',
            self::ViewAdvancedMetrics => 'viewAdvancedMetrics',
            self::ViewOrgMetrics => 'viewOrgMetrics',
            self::ViewSearcherMetrics => 'viewSearcherMetrics',
            self::ViewOccupantMetrics => 'viewOccupantMetrics',
            self::UserManage => 'manageUsers',
            self::UserCreate => 'createUsers',
            self::UserEdit => 'editUsers',
            self::UserDelete => 'deleteUsers',
            self::UserExport => 'exportUsers',
            self::UserImport => 'importUsers',
            self::OrganizationManage => 'manageOrganizations',
            self::OrganizationCreate => 'createOrganizations',
            self::OrganizationEdit => 'editOrganizations',
            self::OrganizationDelete => 'deleteOrganizations',
            self::OrganizationExport => 'exportOrganizations',
            self::OrganizationImport => 'importOrganizations',
            self::PlanManage => 'managePlans',
            self::PlanCreate => 'createPlans',
            self::PlanEdit => 'editPlans',
            self::PlanDelete => 'deletePlans',
            self::AiUse => 'aiUse',
        };
    }
}
