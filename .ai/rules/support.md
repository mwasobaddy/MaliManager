---
paths:
  - app/Support/DefaultSubRoles.php
---

# Support

## Property-type catalog is org-scoped and seeds without logging

Property types are a per-organization catalog, not a global table — a shared one would let any owner rename "Villa" and silently relabel every other tenant's properties. `DefaultPropertyTypes::createFor()` seeds 52 entries at organization creation; `syncFor()` tops up existing ones additively (syncBundlesFor's rule applies identically: it never removes or renames, so an owner who deleted a type keeps it deleted). Both run inside `Activity::withoutLogging()` — 52 rows per organization would bury the audit log, and an owner adding a type by hand still logs. `PropertyType` is bound by id, not slug, because properties reference it with `property_type_id`; the slug is a lookup key, so a rename must not 404 shared links. `property_type.manage` is a read/picker gate only — it goes in the Caretaker and Agent bundles alongside `property.manage`, never the write keys.

## Sub-role bundles only apply at org creation; syncBundlesFor repairs existing orgs
Bundles apply only at org creation (DefaultSubRoles::createFor via TenantService::createOrganization). Adding a permission to a bundle later does NOT reach existing orgs — syncBundlesFor() repairs them and RolesAndPermissionsSeeder calls it per organization, so that seeder belongs in the deploy path. syncBundlesFor is additive only (syncWithoutDetaching): it never removes a permission an owner granted/removed by hand, and re-running it returns 0. Caretaker ships property.manage because AuthLanding sends staff to the GET /properties picker and the sub-permission gate otherwise bounced them straight back out — property.manage is a READ/picker gate only (create is property.create and PropertyController::create/store are owner-only), so granting it is not a privilege escalation. Always check which default bundles hold a permission before adding a sub-permission gate to a route.
