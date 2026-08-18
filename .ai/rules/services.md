---
paths:
  - 'app/Services/**'
  - app/Services/TenantService.php
---

# Services

## Service layer conventions
All business logic with side effects goes in App\Services extending App\Services\Service. Wrap writes in $this->transaction(...) and persist with $this->save() (throws on false) / $this->delete(). Return the affected model. Controllers stay thin and call services. Use App\Services\TenantService::createOrganization() for creating an org's tenancy (registry row + org + default sub-roles + owner membership).

## TenantService auto-creates the org subdomain
createOrganization() now also creates the org subdomain {slug}.malimanager.test (base from config('tenancy.subdomain_base')) via $tenant->domains()->create(). Tests asserting onboarding redirects expect the tenant URL http://{slug}.malimanager.test/properties/create.
