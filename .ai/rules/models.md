---
paths:
  - 'app/Models/**'
---

# Models

## Platform roles vs org sub-roles
Platform roles (admin, organization-owner, tenant) use spatie/laravel-permission on User. Organization-internal access uses custom sub-roles (sub_roles/sub_permissions/org_user/delegations tables) because sub-role->permission assignments are per-org and per-asset delegation is required — spatie's teams feature can't express this. Check org access via User::hasSubPermission(Organization, SubPermissionKey); owners bypass checks. See app/Support/DefaultSubRoles.php for default bundles.
