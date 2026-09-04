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

## Auth routes are prefixed /auth

All auth URLs live under /auth/: Fortify prefix config is 'auth' (config/fortify.php), so login/register/password-reset/two-factor/passkeys are /auth/*. Custom routes: POST /auth/login, GET|POST /auth/otp (+ /auth/otp/resend), GET|POST /auth/onboarding. Socialite stays /auth/{provider}/redirect|callback. web.php loads after Fortify so its POST /auth/login overrides Fortify's store.

## Tenant property routes + mandatory first property

Tenant property routes live under routes/tenant.php inside the tenancy group: GET /properties (picker), GET /properties/create, POST /properties (create + units), GET|POST /properties/{property:slug}(/units). Group is auth + has-property (EnsureOrganizationHasProperty) which blocks org owners with no properties until they create the first one. Wayfinder regenerated with php artisan wayfinder:generate.
