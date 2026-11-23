---
paths:
  - 'app/Services/**'
  - app/Services/TenantService.php
  - app/Services/OrganizationDashboardService.php
---

# Services

**Services answer questions and wrap collaborators. Write use cases live in `app/Actions`** — see
`.ai/rules/actions.md`.

The base class `App\Services\Service` is still the shared home of `$this->transaction()`,
`$this->save()` and `$this->delete()`. Actions extend it too, so persistence behaviour is identical in
both; the base class name is historical.

## Service layer conventions

- A service owns **queries, aggregates and cross-cutting collaborators**: dashboard payloads,
  reporting, access checks, third-party clients. It does not own a single write use case.
- **Do not add a new write method to a service.** That is an Action.
- Existing write methods are **delegates** to an Action, kept only until their callers migrate.
  Deleting one is safe once `grep` finds no callers.
- Wrap multi-step work in `$this->transaction(...)` and persist with `$this->save()` (throws on false)
  / `$this->delete()`. Return the affected model.
- Controllers call Actions for writes and services for reads.
- Use `App\Services\TenantService::createOrganization()` for creating an org's tenancy (registry row +
  org + default sub-roles + owner membership).

## TenantService auto-creates the org subdomain

createOrganization() also creates the org subdomain `{slug}.malimanager.test` (base from
`config('tenancy.subdomain_base')`) via `$tenant->domains()->create()`. Tests asserting onboarding
redirects expect the tenant URL `http://{slug}.malimanager.test/properties/create`.

## Trash: one registry, tiered scope, writes in Actions

Soft-deletable modules register in `app/Support/TrashModules.php` (model, label, destroy gate, scope, optional `tier: gate`). `TrashService` only reads: tier scoping is platform-admin → everything, owner → whole org, everyone else → own `created_by` rows. Central trash page per context; bulk/restore/force/empty endpoints reuse each module's single-item guards and report per-row skips instead of failing the batch. Guards that must survive: scope through trashed parents with `withTrashed` (see actions.md), restore refuses on slug/email/name clash and on trashed parents, force-delete clears media first, staff restore revives the login only (team access needs a re-invite), roles are gate-tiered (no creator column) and keep pivots until force. Excluded by design: leases (below), agreement templates (hard delete), roles' built-ins (protected).

## Scope org dashboard aggregates to accessible properties

Every aggregate/stats/series in the organization dashboard (expense totals, maintenance, leases, flags,
available years) must be scoped to the current user's accessible property IDs from
`PropertyAccessService::organizationsWithProperties`. Owners see all; staff only their delegated
properties. Do not query org-wide without that restriction, or staff leak other properties' figures.
