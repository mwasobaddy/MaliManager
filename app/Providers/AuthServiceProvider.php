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
        Gate::define('accessAdminDashboard', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::AccessAdminDashboard->value));

        Gate::define('viewAdvancedMetrics', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::ViewAdvancedMetrics->value));
        Gate::define('viewOrgMetrics', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::ViewOrgMetrics->value));
        Gate::define('viewSearcherMetrics', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::ViewSearcherMetrics->value));
        Gate::define('viewOccupantMetrics', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::ViewOccupantMetrics->value));

        // Central user management module.
        Gate::define('manageUsers', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::UserManage->value));
        Gate::define('createUsers', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::UserCreate->value));
        Gate::define('editUsers', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::UserEdit->value));
        Gate::define('deleteUsers', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::UserDelete->value));
        Gate::define('exportUsers', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::UserExport->value));
        Gate::define('importUsers', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::UserImport->value));

        // Central organization management module.
        Gate::define('manageOrganizations', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::OrganizationManage->value));
        Gate::define('createOrganizations', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::OrganizationCreate->value));
        Gate::define('editOrganizations', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::OrganizationEdit->value));
        Gate::define('deleteOrganizations', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::OrganizationDelete->value));
        Gate::define('exportOrganizations', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::OrganizationExport->value));
        Gate::define('importOrganizations', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::OrganizationImport->value));

        // Central plan management module.
        Gate::define('managePlans', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::PlanManage->value));
        Gate::define('createPlans', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::PlanCreate->value));
        Gate::define('editPlans', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::PlanEdit->value));
        Gate::define('deletePlans', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::PlanDelete->value));

        Gate::define('aiUse', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::AiUse->value));

        // Central subscription income module.
        Gate::define('manageSubscriptionPayments', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::SubscriptionManage->value));
        Gate::define('createSubscriptionPayments', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::SubscriptionCreate->value));
        Gate::define('editSubscriptionPayments', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::SubscriptionEdit->value));
        Gate::define('deleteSubscriptionPayments', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::SubscriptionDelete->value));

        // Central platform expense module.
        Gate::define('managePlatformExpenses', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::PlatformExpenseManage->value));
        Gate::define('createPlatformExpenses', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::PlatformExpenseCreate->value));
        Gate::define('editPlatformExpenses', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::PlatformExpenseEdit->value));
        Gate::define('deletePlatformExpenses', fn (?User $user) => $user?->hasPermissionTo(PlatformPermissionKey::PlatformExpenseDelete->value));

        Gate::policy(Lease::class, LeasePolicy::class);
    }
}
