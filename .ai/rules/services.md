---
paths:
  - 'app/Services/**'
---

# Services

## Service layer conventions
All business logic with side effects goes in App\Services extending App\Services\Service. Wrap writes in $this->transaction(...) and persist with $this->save() (throws on false) / $this->delete(). Return the affected model. Controllers stay thin and call services. Use App\Services\TenantService::createOrganization() for creating an org's tenancy (registry row + org + default sub-roles + owner membership).
