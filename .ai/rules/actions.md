---
paths:
  - app/Actions/**
  - app/Data/**
  - app/Services/**
  - app/Http/Controllers/**
---

# Where logic goes: Services, migrating to Actions

**Decided: this app is migrating write use cases from `App\Services` into `app/Actions`.**
Query and aggregate methods stay in services. See `references/migrating-services-to-actions.md` in
the skill.

## The boundary

| Method kind | Lives in |
| --- | --- |
| Write use case (`create`, `update`, `softDelete`, `decide`) | `app/Actions/{Domain}/{VerbNoun}` — one `handle()` |
| Query / aggregate (`payload`, `propertyStats`, reporting) | Stays in the service |
| Pure predicate (`canTransition`) | Enum or model |

`actions.md` applies during the migration. `services.md` still describes the base class and the
`$this->transaction()` / `$this->save()` conventions — **Actions extend the same base**, so
persistence behaviour does not change during extraction.

## While migrating

- Move bodies **verbatim**. No renames or refactors in the same commit.
- Leave the service method as a delegate until the last caller moves, then delete it.
- One use case per commit.
- Never edit a failing characterization test to match new behaviour — decide deliberately.

## Do not

- Convert query services (`DashboardService`, `Reporting`, `SearchService`, `PropertyAccessService`).
- Do a big-bang rewrite.
- Add a new write method to a service once its domain has started migrating — put it in an Action.

