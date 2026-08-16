---
paths:
  - 'app/Http/Controllers/Admin/**'
---

# Admin

## Admin panel gated by `admin` alias; impersonate redirects to tenant domain
Admin routes (admin.dashboard, admin.impersonate) live under the `admin` middleware alias (EnsureUserIsPlatformAdmin), which checks PlatformRole::Admin via spatie hasRole and aborts 403 otherwise. Impersonation generates a Stancl token via tenancy()->impersonate($tenant, $userId, '/dashboard', 'web') and redirects to `{scheme}://{tenant-domain}/impersonate/{token}` — it requires the org's tenant to have a domain. Users outside the org and other admins cannot be impersonated.
