---
paths:
    - 'app/Models/**'
---

# Models

## Platform roles vs org sub-roles

Platform roles (admin, organization-owner, tenant) use spatie/laravel-permission on User. Organization-internal access uses custom sub-roles (sub_roles/sub_permissions/org_user/delegations tables) because sub-role->permission assignments are per-org and per-asset delegation is required — spatie's teams feature can't express this. Check org access via User::hasSubPermission(Organization, SubPermissionKey); owners bypass checks. See app/Support/DefaultSubRoles.php for default bundles.

## OrganizationUser is an auto-incrementing pivot

The organization_user table has an id column, so App\Models\OrganizationUser sets public $incrementing = true (Pivot defaults to false). Saving a new membership without this leaves id null, which breaks downstream rows that reference organization_user_id (e.g. delegations).

## Never use wherePivot inside whereHas

`wherePivot()` inside a `whereHas('organizations', ...)` callback silently matches nothing in this codebase (verified against organization_user with is_owner = 1). Constrain the joined pivot table directly instead: `->where('organization_user.is_owner', true)`.

## Roles trash with pivots kept; leases stay out of trash

Custom roles soft-delete (`App\Models\Role` uses SoftDeletes, `roles.deleted_at` lives in the permission-tables migration): `DeleteRole::handle` trashes with users and permissions attached so restore is whole, and only `DeleteRole::force` (trash permanent-delete/empty) detaches, reassigns holders to `searcher`, and hard-deletes. Flush spatie's permission cache on every transition or effective access lags the trash state. Built-ins are protected from all of it.

Leases are deliberately NOT trashable: `payments.lease_id` and `deposit_refunds.lease_id` are `cascadeOnDelete`, so a permanent delete would wipe financial history, and soft-deleted leases would need excluding from every arrears and reporting query. Ended status (`status=ended`) is the lease archive — it keeps balances and never goes overdue.
