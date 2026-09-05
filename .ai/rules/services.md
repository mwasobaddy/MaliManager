---
paths:
  - 'app/Services/**'
  - app/Services/TenantService.php
  - app/Services/OrganizationDashboardService.php
---

# Services

## Service layer conventions

All business logic with side effects goes in App\Services extending App\Services\Service. Wrap writes in $this->transaction(...) and persist with $this->save() (throws on false) / $this->delete(). Return the affected model. Controllers stay thin and call services. Use App\Services\TenantService::createOrganization() for creating an org's tenancy (registry row + org + default sub-roles + owner membership).

## TenantService auto-creates the org subdomain

createOrganization() now also creates the org subdomain {slug}.malimanager.test (base from config('tenancy.subdomain_base')) via $tenant->domains()->create(). Tests asserting onboarding redirects expect the tenant URL http://{slug}.malimanager.test/properties/create.

## Scope org dashboard aggregates to accessible properties
Every aggregate/stats/series in the organization dashboard (expense totals, maintenance, leases, flags, available years) must be scoped to the current user's accessible property IDs from PropertyAccessService::organizationsWithProperties. Owners see all; staff only their delegated properties. Do not query org-wide without that restriction, or staff leak other properties' figures.
