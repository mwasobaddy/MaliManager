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

    public function label(): string
    {
        return match ($this) {
            self::ViewAudit => 'View audit log',
            self::ExportAudit => 'Export audit log',
            self::ManageRoles => 'Manage roles',
        };
    }

    public function gate(): string
    {
        return match ($this) {
            self::ViewAudit => 'viewAnyAudit',
            self::ExportAudit => 'exportAudit',
            self::ManageRoles => 'manageRoles',
        };
    }
}
