---
paths:
  - 'app/Http/Controllers/Tenant/**'
---

# Tenant

## Org property flow is scoped to the current tenancy
Tenant controllers read the org from TenancyContext::organization() and never trust route params for scoping. Property route-model binding is org-scoped via Property::resolveRouteBindingQuery (slug binding). Plan limits (properties_limit / units_limit) are enforced in App\Services\PropertyService::create/addUnit, which throw DomainException caught by controllers into a 'plan' validation error.

## Property management URLs are top-level `/{property:slug}/...`
Property-scoped routes live at the top level of the tenant subdomain, without a /properties prefix: `/{property:slug}/dashboard` (tenant.properties.dashboard) and `/{property:slug}/units` (tenant.properties.units.store). The /properties* routes are org-level (picker: index, create, store) and are excluded from the property context. AuthLanding sends a single-property org straight to `/{slug}/dashboard`; multi-property orgs land on the /properties picker. The picker (index + create) renders with TenantPickerLayout (no app sidebar); property pages render with AppLayout so the navbar appears only when a property is selected. HandleInertiaRequests shares tenant.organization and tenant.property; AppSidebar reads tenant.property to build property-scoped nav (Dashboard + Switch property).
