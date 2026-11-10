---
paths:
  - bootstrap/app.php
---

# Bootstrap

## Only 404 gets a branded error page
Authorization failures must stay redirects-with-toast (test suite pins that contract), so the exception responder maps only full-page 404s to errors/404. errors/403, errors/500, errors/429 components exist but are intentionally unwired. Error pages must opt out of AppLayout in app.tsx (no shared props on routing exceptions).

## Branded error pages for full-page loads outside debug
errors/404 always; errors/403, errors/429, errors/500 only when the request is NOT an Inertia visit AND app.debug is off (tests run debug-on, Inertia visits keep toast-and-back). Never map ValidationException (not an HttpException) — that broke the suite. Error pages opt out of AppLayout in app.tsx (no shared props on routing exceptions). 403 keeps its impersonation-warning branch first.
