---
paths:
  - 'app/Http/Middleware/**'
---

# Middleware

## Suspended organizations are blocked by middleware on tenant routes
EnsureOrganizationIsActive (alias `organization.active`) blocks tenant requests for a suspended organization — apply it to BOTH groups in routes/tenant.php (`['auth','organization.active','has-property']` and the org-level `['auth','organization.active']`), otherwise land parcels/payments/staff/audit stay reachable while suspended. A soft-deleted org resolves to null via TenancyContext::organization() and is deliberately NOT treated as suspended. Suspension is set via PATCH organizations/{organization}/status -> OrganizationManagementService::setStatus(), which also deletes the org members' rows from `sessions` so an open tab dies immediately rather than at cookie expiry. NOTE when testing: stancl caches the resolved Tenant and its loaded organization relation per PHP process, so flipping status between two simulated requests in one test reads a stale model — set the status BEFORE the first request.
