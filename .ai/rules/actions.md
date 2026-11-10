---
paths:
  - 'app/Actions/**'
  - 'app/Data/**'
  - 'app/Services/**'
  - 'app/Http/Controllers/**'
  - app/Services/NotificationService.php
  - app/Services/RentArrearsService.php
  - 'app/Http/Controllers/**/*.php'
---

# Write logic lives in Actions

**This app has been migrated. Write use cases live in `app/Actions/{Domain}/{VerbNoun}`; services now
hold queries and aggregates.** See `references/migrating-services-to-actions.md` in the
`laravel-feature-slice` skill for the procedure this followed.

## The boundary

| Method kind | Lives in |
| --- | --- |
| Write use case (`create`, `update`, `softDelete`, `decide`, `moveOut`) | `app/Actions/{Domain}/` — one `handle()` |
| Query / aggregate (`payload`, `propertyStats`, reporting, dashboards) | Stays in the service |
| Pure predicate (`canTransition`) | Enum or model |
| Cross-cutting collaborator (tenancy, access, audit) | `app/Services` |

## Rules for new work

- A new write path is an Action. Do not add a write method to a service.
- Actions extend `App\Services\Service`, so `$this->transaction()`, `save()` and `delete()` behave
  exactly as they do in services.
- Shared private helpers live in `app/Actions/{Domain}/Concerns/{Entity}Helpers.php` and are used by
  both the Action and the service. Do not duplicate them.
- Controllers call the Action directly for new work. The existing service methods are **delegates**
  kept so current callers keep working; they are removed as callers migrate.

## Do not

- Convert a query service into an Action.
- Add a second representation of the same helper.
- Call a private helper across classes — if a helper is needed by more than one class, it belongs in
  the trait.

## Tenancy

Tenancy is ambient. Pass `organizationId` in the DTO rather than letting an Action read the container
context; keep tenant resolution in policies and middleware.

## Notify via the recipient's channel preference
Outbound user notifications (billing cron, future rent reminders) go through NotificationService, which routes by the RECIPIENT's users.notification_channel (email|sms) — never the sender's. SMS needs the recipient's phone; without one it falls back to email with a log line. New notifiers call the service; they do not pick channels.

## Only received payments count toward arrears
Rent balances are expected-vs-paid per lease: pending and refunded payments never reduce a balance, deposits live in their own model, and advance payments count (they cover dues). Money stays decimal via bcmath. The M-Pesa/card integration will write received automatically — no new payment status is needed for gateway money.

## Inertia view names must match page files exactly
Inertia::render() does no index fallback: 'searcher/assistant' 500s when the file is searcher/assistant/index.tsx. When adding a render target, assert the component in a Feature test (see AssistantViewsTest) — the Vite manifest miss only surfaces at runtime.
