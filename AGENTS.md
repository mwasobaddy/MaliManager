# MaliManager

Multi-tenant SaaS for property/unit/land management (Kenya). Laravel 13 (PHP 8.5) + Inertia v3 + React 19 + Tailwind v4. Single-DB tenancy (`stancl/tenancy`), Fortify + Socialite auth, Pest 5, Pint, Larastan level 7.

## Read this first

- `.ai/rules/` is load-bearing, not optional. Before planning or editing, open `.ai/rules/index.md`, read every rule file whose globs match your paths, and `grep -rin 'keyword' .ai/rules` for what path matching misses.
- Big-picture spec: `project.md`. AI subsystem: `reporting-ai.md`. Table/UI patterns: `table-ui.md`. Product tours: `doc/product-tours.md`.
- Stack versions: check `composer.json` / `package.json` directly; do not assume.

## Commands

- Full check (what CI runs): `composer ci:check` (= `npm run lint:check` + `format:check` + `types:check` + `test`). CI workflow: `.github/workflows/tests.yml` (PHP 8.5, Node 22, `composer setup` then `composer ci:check`).
- Full test: `composer test` (= `config:clear` + `lint:check` + `types:check` + `php artisan test`). Single slice: `php artisan test --filter=Name`.
- After any PHP edit: `vendor/bin/pint --dirty`. After frontend edits: `npm run format`, `npm run lint`, `npm run types:check`.
- Dev servers: `composer run dev`. Fresh clone: `composer setup` (installs, sets git hooks, migrates, builds).
- Regenerate routes after touching `routes/`: `php artisan wayfinder:generate --with-form` — the CLI omits `.form` variants without the flag and breaks `useForm`/`<Form>` pages (`TS2339`); `formVariants: true` in `vite.config.ts` only covers dev-time generation. Call `.form` on the function object (`update.form(id)`), never on the call result.

## Architecture

- Writes live in `app/Actions/{Domain}/` (one `handle()` each, extending `App\Services\Service` for `transaction()`/`save()`/`delete()`). Do not add write methods to services — services own queries/aggregates; existing service writers are delegates pending migration. Controllers stay thin: parse input → call Action/service → render/redirect. Never add generic `catch (Throwable)` in controllers; `bootstrap/app.php` already maps `DomainException`/403s/422s to error-toast redirects.
- Layout is area-based, not per-module: `app/Http/Controllers/Tenant/`, `app/Http/Requests/Tenant/`, flat `app/Services/`. Central routes (`platform.*`) live in `routes/platform.php` (required from `routes/web.php`); tenant routes in `routes/tenant.php`. Org dashboard is `GET /overview` (`tenant.overview`, import from `@/routes/tenant`) — never `/dashboard`, that is the central route.
- Tenancy is ambient single-DB: `DatabaseTenancyBootstrapper` disabled; tenant tables carry `tenant_id` + `BelongsToTenant`; read context via `App\Support\TenancyContext`. Tenant routes need `InitializeTenancyByDomain` + `PreventAccessFromCentralDomains`. Central tables (`users`, `organizations`, `persons`, `plans`, `sub_roles`) are NOT tenant-scoped.
- Auth URLs are all `/auth/*` (Fortify prefix `auth`; `web.php` overrides Fortify's login store). Only the `dashboard` route carries the `onboarded` middleware — do not gate other pages with it. Orgs are created via `TenantService::createOrganization()` (also auto-creates `{slug}.malimanager.test` subdomain); it also provisions default sub-roles.
- Platform roles (spatie on `User`) vs org access (custom `sub_roles`/`delegations` tables — spatie teams can't express per-asset delegation). Check via `User::hasSubPermission($org, SubPermissionKey)`; owners bypass. `organization_user` has an `id` column so the model sets `$incrementing = true`. Never use `wherePivot` inside `whereHas` — constrain `organization_user.is_owner` directly.

## Gotchas that have bitten before

- Impersonation: Stancl's `UserImpersonation` keeps no record of the admin. `PlatformController::startImpersonating()` writes session key `impersonated_by` before the swap; `ImpersonationController::stop()` restores from it, is deliberately NOT behind `admin` middleware, and its URI must exist on the central domain only (registering it in both route files collides and the tenant copy wins).
- Money: single representation — `decimal(14,2)` columns, `'decimal:2'` casts, carry decimal strings in DTOs/Actions, `bcmath` for arithmetic. Never integer cents or floats.
- Tests: `tests/Pest.php` gives `RefreshDatabase` to `Feature` only; `Unit/Actions` files opt in per-file with `uses(RefreshDatabase::class)`. Permission denials redirect with an error toast — assert `assertRedirect()` + `assertErrorToast()`, never `assertForbidden()`. Test DB is SQLite `:memory:`, prod is MySQL: missing-column selects return null on SQLite but 500 on MySQL (regression tests need a related record), and `sum()` returns float vs decimal-string (compare with `(float)` casts, never string identity).
- Frontend: create/edit/view are `ResponsiveOverlay` sheets driven by the URL via `useOverlayParam()` (never `useState`); delete is the only modal (`ConfirmDeleteOverlay`) and is not the gate — the route permission plus a `Destroy*Request` with `current_password` does the real check. Never stack two overlays. Record URLs (`/occupants/5`) redirect to query-param form; constrain with `whereNumber` or they swallow `/create`. Heavy props use `Inertia::optional()` + `router.reload({ only: [...] })`; flash data reads from `page.flash`, not `page.props`.
- `Inertia::render()` has no index fallback: view name must match the page file exactly (assert new targets in a Feature test). Dashboard aggregates must be scoped to `PropertyAccessService::organizationsWithProperties` IDs or staff leak other properties' figures. Tenant file paths are owned by `App\Support\StorageLayout`; media conversions by `TenantPathGenerator`.

## Commits

`.githooks/commit-msg` enforces `type(scope): imperative lowercase subject` (no full stop, ~72 chars), blank line, then what was wrong / what changed / constraint, closing with what was actually run (`Verified by tests:` / `Verified in the browser:`). Never claim an unverified check.
