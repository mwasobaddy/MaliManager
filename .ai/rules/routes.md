---
paths:
  - routes/web.php
  - routes/tenant.php
---

# Routes

## Onboarding gates the dashboard route only
The dashboard route is gated by the `onboarded` middleware alias (registered in bootstrap/app.php), which redirects un-onboarded users to `onboarding.show`. Only the dashboard is gated; other authenticated pages (settings) are not. Users are marked onboarded via `User::markOnboarded()` after registration.

## Impersonation route lives in routes/tenant.php (tenant.impersonate)
Tenant impersonation route is `GET /impersonate/{token}` (route name tenant.impersonate) in routes/tenant.php, calling `UserImpersonation::makeResponse($token)`. It must stay inside the InitializeTenancyByDomain + PreventAccessFromCentralDomains group. The feature is enabled in config/tenancy.php 'features'. After login the token is deleted and the user lands on the redirect_url stored when the token was created (admin sends '/dashboard').
