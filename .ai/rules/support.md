---
paths:
  - app/Support/DefaultSubRoles.php
---

# Support

## The property-type catalog is platform-wide, not org-scoped

Property types are central configuration: one table with no `organization_id`, managed by platform admins under `property-type.*` **PlatformPermissionKeys** (`app/Enums/PlatformPermissionKey.php`), routed at `/platform/property-types`, with the controller in `app/Http/Controllers/Platform/` and the page at `resources/js/pages/platform/property-types/`. There are no `property_type.*` SubPermissionKeys and nothing in DefaultSubRoles' bundles — do not reintroduce them. `PropertyTypeCatalog::seed()` seeds the 52-entry default catalog once, globally, and is additive-only (never renames or deletes, so an admin's customisation survives re-seeds). Tenant property forms read the same table with no org scope; `StorePropertyRequest` guards `property_type_id` with `Rule::exists('property_types','id')->whereNull('deleted_at')` so a soft-deleted type cannot be picked from a stale form. Import is report-on-duplicate, not upsert. `PropertyType` is bound by id, not slug — properties reference it with `property_type_id`, so a rename must not 404 shared links.

## Sub-role bundles only apply at org creation; syncBundlesFor repairs existing orgs
Bundles apply only at org creation (DefaultSubRoles::createFor via TenantService::createOrganization). Adding a permission to a bundle later does NOT reach existing orgs — syncBundlesFor() repairs them and RolesAndPermissionsSeeder calls it per organization, so that seeder belongs in the deploy path. syncBundlesFor is additive only (syncWithoutDetaching): it never removes a permission an owner granted/removed by hand, and re-running it returns 0. Caretaker ships property.manage because AuthLanding sends staff to the GET /properties picker and the sub-permission gate otherwise bounced them straight back out — property.manage is a READ/picker gate only (create is property.create and PropertyController::create/store are owner-only), so granting it is not a privilege escalation. Always check which default bundles hold a permission before adding a sub-permission gate to a route.
