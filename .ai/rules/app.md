---
paths:
    - 'app/**'
---

# App

## Single-DB tenancy conventions

Single-database tenancy: stancl DatabaseTenancyBootstrapper is disabled. Tenant data tables carry a `tenant_id` string column referencing `tenants.id` (uuid) and use Stancl\Tenancy\Database\Concerns\BelongsToTenant (auto scopes by current tenant + fills tenant_id on create). Access current context via App\Support\TenancyContext (tenantId(), organization(), domain()). Tenant routes must use InitializeTenancyByDomain + PreventAccessFromCentralDomains (see routes/tenant.php). Central tables (organizations, persons, plans, users, sub_roles) are NOT tenant-scoped — only domain data tables are.
