---
paths:
  - 'app/Http/Controllers/Tenant/**'
---

# Tenant

## Org property flow is scoped to the current tenancy
Tenant controllers read the org from TenancyContext::organization() and never trust route params for scoping. Property route-model binding is org-scoped via Property::resolveRouteBindingQuery (slug binding). Plan limits (properties_limit / units_limit) are enforced in App\Services\PropertyService::create/addUnit, which throw DomainException caught by controllers into a 'plan' validation error.
