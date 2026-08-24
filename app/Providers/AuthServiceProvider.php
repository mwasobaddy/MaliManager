<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\PlatformPermissionKey;
use App\Enums\SubPermissionKey;
use App\Models\Lease;
use App\Models\User;
use App\Policies\LeasePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Platform-wide authorization gates that sit outside the
 * organization-scoped sub-permission system. Access is granted through
 * central permissions (assignable to roles on the settings/roles page),
 * with the admin role holding all of them by default.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('viewAnyAudit', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::ViewAudit->value));

        Gate::define('exportAudit', function (?User $user, $organization = null) {
            if (! $user) {
                return false;
            }

            // Admins always export everything.
            if ($user->hasPermissionTo(PlatformPermissionKey::ExportAudit->value)) {
                return true;
            }

            // Organization members with the audit sub-permission export
            // only their own organization's audit (caller enforces scope).
            return $organization !== null
                && $user->hasSubPermission($organization, SubPermissionKey::AuditView);
        });

        Gate::define('manageRoles', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::ManageRoles->value));

        Gate::policy(Lease::class, LeasePolicy::class);
    }
}
