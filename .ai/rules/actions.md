---
paths:
  - app/Actions/**
  - app/Data/**
  - app/Services/**
  - app/Http/Controllers/**
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
