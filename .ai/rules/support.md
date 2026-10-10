---
paths:
  - app/Support/DefaultSubRoles.php
---

# Support

## Sub-role bundles only apply at org creation; syncBundlesFor repairs existing orgs
Bundles apply only at org creation (DefaultSubRoles::createFor via TenantService::createOrganization). Adding a permission to a bundle later does NOT reach existing orgs — syncBundlesFor() repairs them and RolesAndPermissionsSeeder calls it per organization, so that seeder belongs in the deploy path. syncBundlesFor is additive only (syncWithoutDetaching): it never removes a permission an owner granted/removed by hand, and re-running it returns 0. Caretaker ships property.manage because AuthLanding sends staff to the GET /properties picker and the sub-permission gate otherwise bounced them straight back out — property.manage is a READ/picker gate only (create is property.create and PropertyController::create/store are owner-only), so granting it is not a privilege escalation. Always check which default bundles hold a permission before adding a sub-permission gate to a route.
