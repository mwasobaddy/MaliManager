---
paths:
  - 'app/Http/Controllers/**/*.php'
---

# Controllers

## Impersonation must record the admin before swapping the session user
Stancl's `UserImpersonation` is fire-and-forget: `makeResponse()` calls `Auth::guard('web')->loginUsingId()` and overwrites the session user, deleting the token and keeping the previous identity NOWHERE. Without an explicit record of who is being impersonated (session key `impersonated_by`, written by `PlatformController::startImpersonating()` before the swap), an admin who impersonates an owner is permanently stranded on the owner account — the only exit is logout + OTP login. `ImpersonationController::stop()` restores from that key; it is deliberately NOT behind the `admin` middleware, because during an impersonation the current user is the owner and would be refused. Register its URI on one domain only — central and tenant route files both defining `POST /impersonation/stop` silently collide and the tenant copy (carrying PreventAccessFromCentralDomains) wins, making the central stop unreachable.
