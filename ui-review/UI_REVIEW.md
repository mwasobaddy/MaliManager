# MaliManager UI Review — Round 2 (current codebase, from scratch)

> **Fix log — Now batch (2026-11-12).** Fixed + test-covered + verified live:
> R2-001 (occupant-person eager loads + `tenant_name` via `person.full_name`;
> regression test `the refunds page resolves the tenant name through the
> occupant person`), R2-002 (`$user = $request->user()` in `edit()`; regression
> test `the land parcel edit page renders for the owner`, proven to 500 without
> the fix), R2-005 (`organisation/…` layout keys; print views verified
> chromeless live), R2-006 (`Breadcrumbs` renders href-less non-final crumbs as
> text + added missing `href`s on unit/land-parcel crumbs), R2-007 (`.layout`
> breadcrumbs added to 17 pages: reports, inspections index/create, payments
> index/form, deposit-refunds, agreement-templates index/form, assistant
> index/history/conversation, drafting, ai-settings, expenses form, leases,
> meter-readings; all verified live), R2-015 (tab values `home`/`dashboard`;
> valid `aria-controls` + navigation verified live). Full suite: 671 passed,
> 1 pre-existing skip. Evidence: `R2-VERIFY-*.png`.
> Scope decision: staff access model = assigned-property scoping (applies to the
> Next batch: R2-003/R2-004).
>
> **Fix log — Next batch (2026-11-12).** Assigned-property scoping enforced:
> routes re-gated (expenses → `expense.manage`, maintenance/notes →
> `maintenance.*`; reports/assistant/drafting/ai-settings/templates stay on
> `lease.manage_templates`), `LeaseController::authorizePropertyAccess`
> delegation-aware, maintenance + deposit-refund lists scoped to delegated
> properties with record-level checks (`MaintenanceController`,
> `DepositRefundPolicy`, `StoreDepositRefundRequest`,
> `MaintenanceRequestNotePolicy`), sidebar `canViewReports`/drafting link and
> tenant assistant widget aligned to `lease.manage_templates`. Formats: new
> `formatDate()` helper; units + platform-expenses money via `formatMoney`;
> payments/expenses dates via `formatDate`; audit show via `formatDateTime`;
> receipt/dashboard/parcel status capitalization. Payments: `—` tenant
> placeholder, fallback aria-label, receipt title falls back to unit ·
> property. Headings: sr-only `h1` on all assistant pages + personal-ai.
> Search button `aria-label="Search (⌘K)"`. Landing wrapped in `<main>`.
> Payment validation uses display names (`lease`, `payment date`). Verified
> live as delegated staff (maintenance/leases open; reports/expenses denied +
> hidden) and owner (money/dates/receipt/audit/`main`/assistant/personal-ai/
> validation). Full suite: 678 passed, 1 pre-existing skip.
> **Fix log — Later batch (2026-11-13, polish).** Suspended copy de-duplicated
> (layout keeps the guidance, body keeps the fact). Land-parcel show headings
> h3 → h2 (Sections, Managers, Danger zone). Print views: single h1 —
> reports/print + inspections/show toolbars keep only action buttons while the
> printable header carries the h1. Guest filters overlay describes the form
> when empty (`Filter by rent, type and sort order.`). Row action buttons hit
> 44px on coarse pointers (`coarse:min-h-11 coarse:min-w-11`, measured 44×44
> live). Landing tabs: real tabpanel wrapper + tab ids (Lighthouse now
> 100/100/100/100, 0 failures). Landing wrapped in `<main>` (Now batch) also
> confirmed by the same run. **R2-018 disposition: environmental, not an app
> defect.** The 404-with-content comes from Valet's per-site nginx config
> (`location = /favicon.ico` with no handler + `root /`, then `error_page 404`
> re-serving the real file through Valet with the status preserved); the app
> ships and declares the file correctly, and production static serving is
> unaffected. Local remedy (needs an nginx reload the sandbox cannot issue):
> delete the handler-less `location = /favicon.ico` lines from
> `~/.config/valet/Nginx/malimanager.test` and reload. No in-repo change;
> favicon.svg already serves 200. Full suite: 678 passed, 1 pre-existing skip.
> Evidence: `R2-VERIFY-*.png`.
>
> **Corrections to the original findings.** Re-investigation during the fix
> showed R2-003/R2-004 as first written were imprecise:
> `OccupantController::authorizePropertyAccess` was already delegation-aware —
> only the lease gate was owner-only (now fixed). The test caretaker holds
> zero property delegations, so its occupants/leases denials were correct
> per the delegation design, not gate bugs. The real defects were: (a) the
> `lease.manage_templates` mega-group swallowing the expenses/maintenance
> routes (this was the actual cause of the staff maintenance denial —
> `maintenance.manage` alone could never pass), (b) the owner-only lease
> gate, and (c) sidebar/widget links wider than their route gates.

Date: 2026-11-11. Auditor: OpenCode (Muse Spark). Environment: `https://malimanager.test`
(central) + `https://savannah-estates.malimanager.test` (tenant), seeded data + disposable
test records (all removed afterwards). Round-1 evidence preserved at `doc/ui-review/`;
this round uses fresh `R2-` IDs and fresh screenshots in `ui-review/screenshots/`.

## 1. Executive summary

28 findings (2 Critical, 6 High, 13 Medium, 7 Nit). The app is in good shape —
navigation, empty states, validation, toasts, dark mode and responsive behaviour hold
up across roles — but the audit surfaced two hard-broken pages, a staff
permission/nav mismatch, and a set of formatting/consistency drifts.

Top 10 problems:

1. **R2-001 — Deposit-refunds page 500s for everyone** (`occupant:id,name`
   eager-load against a table with no `name` column). Whole module unreachable.
2. **R2-002 — Land-parcel edit page 500s** (`Undefined variable $user`,
   `LandParcelController.php:118`). Parcel can be created but never edited.
3. **R2-003 — Staff sidebar advertises pages the server denies.** Maintenance and
   Reports links render for a caretaker, then bounce with a "You do not have
   permission" toast. Dead-end navigation.
4. **R2-004 — Leases/Occupants controllers are owner-only** (`abort(403)` for any
   non-owner), contradicting the sub-permission model (`lease.manage`,
   `occupant.manage` exist and are granted to staff). Either the gates or the
   permissions are wrong.
5. **R2-005 — Print views render inside app chrome.** The `layout: null`
   exemptions in `app.tsx` use stale `tenant/…` component names while every
   controller renders `organisation/…`, so reports/receipts/agreements print with
   sidebar + header. No `print:hidden` on layout chrome either.
6. **R2-006 — Breadcrumb self-links.** Non-final crumbs without `href` (Units,
   Land parcels) render as links to the *current* page.
7. **R2-007 — ~15 pages have no breadcrumb at all** (no `.layout` config):
   reports, inspections, payments, agreement templates, meter readings, assistant,
   drafting, AI settings, all create/edit forms, etc.
8. **R2-008/009/010 — Formatting drift:** money (`25000.00` vs `KES 25,000` vs
   `KES 5,640.5` vs `KES 180,833.33`), dates (ISO vs US-locale vs `11 Nov 2026`),
   status casing (`received` vs `Received`, `active` vs `Occupied`).
9. **R2-015/016 — Lighthouse a11y (dashboard):** tab values containing `/`
   produce invalid `aria-controls`; search button visible text (`⌘K`) mismatches
   its accessible name. Plus landing page still has no `<main>` landmark
   (R2-017) and favicon serves content with a 404 status (R2-018).
10. **R2-011/012 — Payments rough edges:** empty TENANT cell with no placeholder,
    `Edit payment for null` aria-label, receipt titled `Rent receipt — —`.

Quick wins (< 1h each): R2-006 (one guard in `breadcrumbs.tsx`), R2-015 (tab
values), R2-016 (aria-label), R2-018 (favicon route), R2-020 (validation
attribute name), R2-017 (`<main>` on landing), R2-002 (`$request->user()`),
R2-022 (drop duplicate print h1).

## 2. Scope and method

- **Roles:** guest/logged-out (landing, login, OTP), admin (platform), owner
  (Savannah Estates), staff/caretaker (`staff.savannah-estates.1`), occupant
  (active lease). Full logout isolation via separate browser contexts; OTP login
  per role; seeded owner password used once for the confirm-password gate.
- **Viewports:** 360 / 768 / 1280 / 1920 (+ dark mode spot checks). Overflow
  checked via `scrollWidth` vs `innerWidth` on every key page — no overflow found.
- **States:** default, empty (payments, inspections, land parcels, listings,
  maintenance), error (500s, 404s, denied), success (expense/payment create
  toasts), validation (payment, expense), gating (plan-blocked property create,
  unconfigured AI, suspended page).
- **A11y:** Lighthouse snapshot audits (landing: A95/BP96-100/SEO100;
  dashboard: A95), heading/landmark/ARIA spot checks, focus styles verified in
  code, touch-target measurement on table row actions.
- **Console/network:** swept per role; only the favicon 404 recurs.
- **Writes:** 1 maintenance request (resolved + deleted), 1 land parcel
  (created, shown, deleted), 1 payment (receipt verified, deleted), 1 expense
  (created, edited-viewed, deleted). DB clean afterwards.

## 3. Coverage tracker

Legend: ✅ checked this round · ➖ unreachable/untestable (see §8) · n/a not
applicable to role. All viewports spot-checked on pages marked ✅ (360 always,
768/1920 on tables/dashboards).

| Page / route | Guest | Occupant | Staff | Owner | Admin |
|---|---|---|---|---|---|
| Landing `/` | ✅ | n/a | n/a | n/a | n/a |
| Login / OTP / confirm-password | ✅ | ✅ | ✅ | ✅ | ✅ |
| Guest home `/home` (listings, filters, save search) | ➖ (redirects to login) | ✅ | ✅ | n/a | n/a |
| Listings `listings/{unit}` (unlisted → 404) | ➖ | ✅ | n/a | n/a | n/a |
| My rentals `searcher/rentals` | n/a | ✅ | n/a | n/a | n/a |
| Occupant maintenance `searcher/maintenance` | n/a | ✅ | n/a | n/a | n/a |
| Become-landlord + setup funnel (`/setup/plan`, team/review redirects) | n/a | ✅ | n/a | ✅ | n/a |
| Central dashboard + picker + tabs | n/a | ✅ | ✅ | ✅ | ✅ |
| Property dashboard | n/a | n/a | ➖ (no assignment) | ✅ | n/a |
| Org overview | n/a | n/a | ✅ | ✅ | n/a |
| Properties index / create (plan gate) | n/a | n/a | ✅ (empty, correct) | ✅ | n/a |
| Units index / show | n/a | n/a | ✅ | ✅ | n/a |
| Meter readings | n/a | n/a | ✅ | ✅ | n/a |
| Occupants (table, create/edit/view sheets, move-out) | n/a | n/a | ➖ (denied, see R2-004) | ✅ | n/a |
| Leases (table, remind, agreement) | n/a | n/a | ➖ (denied, see R2-004) | ✅ | n/a |
| Inspections index / create / show(bad id→404) | n/a | n/a | n/a | ✅ | n/a |
| Inspections show with real report | n/a | n/a | n/a | ➖ (no data; needs photos+AI) | n/a |
| Maintenance (staff side, resolve flow) | n/a | n/a | ➖ (denied, see R2-003) | ✅ | n/a |
| Expenses index / create / edit (+row, toast) | n/a | n/a | n/a | ✅ | n/a |
| Payments index / create / validation / receipt | n/a | n/a | n/a | ✅ | n/a |
| Deposit refunds | n/a | n/a | ➖ (500) | ➖ (**500**, R2-001) | n/a |
| Land parcels index / create / show / edit | n/a | n/a | ✅ (index) | ✅ / ✅ / ✅ / ➖ (**500**, R2-002) | n/a |
| Staff index / create+edit overlays | n/a | n/a | ✅ | ✅ | n/a |
| Billing | n/a | n/a | ➖ (hidden, correct) | ✅ | n/a |
| Agreement templates index / create | n/a | n/a | n/a | ✅ | n/a |
| Reports index / CSV / print | n/a | n/a | ➖ (denied, R2-003) | ✅ | n/a |
| Assistant (unconfigured + platform) / drafting / AI settings | n/a | n/a | ✅ | ✅ | ✅ |
| Tenant audit index / show | n/a | n/a | n/a | ✅ | n/a |
| Settings profile / security / appearance / personal-ai | n/a | ✅ (profile) | n/a | ✅ | ✅ (appearance) |
| Platform dashboard / users / orgs / plans / sub-payments / expenses / roles / property-types / audit / assistant | n/a | n/a | n/a | n/a | ✅ |
| Suspended, 404, 403/429/500 branded pages | ✅ (suspended+404 live) | n/a | n/a | n/a | ➖ (branded 403/429/500 only in code; debug mode renders exception pages) |
| Emails (OTP, rent/billing reminders, digest) | ✅ (code-level) | n/a | n/a | n/a | n/a |
| Impersonation flow | n/a | n/a | n/a | n/a | ➖ (deliberately not triggered; would disturb sessions) |

## 4. Findings

Screenshot dir: `ui-review/screenshots/`. Severity: Critical / High / Medium /
Low / Nit. Effort: S (<1h) / M (<1 day) / L (>1 day).

| ID | Page / Route | Role(s) | Viewport | Category | Issue | Evidence | Code location | Severity | Recommendation | Effort | Reference |
|----|--------------|---------|----------|----------|-------|----------|---------------|----------|----------------|--------|-----------|
| R2-001 | `/deposit-refunds` | owner, staff | 1280 | Error handling / backend | Page throws 500 `Unknown column 'name'` on `occupants`: `lease.occupant:id,name`, `requester:id,name`, `decider:id,name` eager-loads plus `with('occupant:id,name')` in `leases()`, but the occupants table has no `name` column. Entire module unreachable. | `R2-017-deposit-refunds-500.png` | `app/Http/Controllers/Tenant/DepositRefundController.php:37-39,85` | Critical | Select the real name columns (or the person relation) and add a feature test hitting the index with an occupant present. | S | — |
| R2-002 | `/land-parcels/{parcel}/edit` | owner | 1280 | Error handling / backend | 500 `Undefined variable $user` — `edit()` references `$user` that is never defined (copy/paste from `show()` which has it). Parcels can be created but never edited. | `R2-025-landparcel-edit-500.png` | `app/Http/Controllers/Tenant/LandParcelController.php:118` (use `$request->user()`) | Critical | One-line fix + smoke test for every tenant edit route. | S | — |
| R2-003 | Sidebar → `/maintenance`, `/reports` | staff | 1280 | Navigation / permissions | Caretaker sidebar shows Maintenance and Reports, but both visits bounce with a "You do not have permission" toast (reproduced twice from clean state, screenshot). Dead-end navigation. Reports: sidebar gate (`canViewReports` = any of expense/maintenance/lease/occupant manage, `app-sidebar.tsx:114-121`) is wider than the route gate (`lease.manage_templates` group). Maintenance denial contradicts the granted `maintenance.manage` sub-permission — needs backend investigation (see open questions). | `R2-031-staff-maintenance-denied.png` | `resources/js/components/app-sidebar.tsx:114-121`, `routes/tenant.php:209-226` (reports group), `routes/tenant.php:273-276` (maintenance group), `app/Http/Middleware/EnsureSubPermission.php` | High | Align nav gates with route gates (single `canVisit*` source); resolve why `maintenance.manage` holders get 403. | M | [Nielsen Norman: error prevention](https://www.nngroup.com/articles/slips/) |
| R2-004 | `/{property}/leases`, `/{property}/occupants*` | staff | 1280 | Permissions | `authorizePropertyAccess()` aborts 403 for every non-owner, so staff holding `lease.manage` / `occupant.manage` / `occupant.edit` can never open leases or occupants (verified: direct URLs denied). Either the owner-only gates or the sub-permission grants are wrong; the sidebar hides these sections, masking the contradiction. | Toast + redirect observed (no screenshot; same toast as R2-031) | `app/Http/Controllers/Tenant/LeaseController.php:142-151`, `app/Http/Controllers/Tenant/OccupantController.php:218-226` | High | Decide: property-scoped staff access (assigned properties) or remove the sub-permissions; make sidebar mirror the decision. | M | — |
| R2-005 | `/reports/print`, `/payments/{id}/receipt`, occupant agreement, inspection show | owner | 1280 | Print | Print views render inside the full app sidebar+header. `app.tsx` exempts `tenant/reports/print`, `tenant/occupants/agreement`, `tenant/inspections/show` from layout, but every controller renders `organisation/…` names — the exemptions never match. Layout chrome also has no `print:hidden`, contradicting the comment in `app.css:526-531`. | `R2-023-report-print-1280.png`, `R2-027-payment-receipt-1280.png`, `R2-022-lease-agreement-1280.png` | `resources/js/app.tsx:14-31`, `resources/css/app.css:533-554` | High | Fix the three layout keys (or key the exemption off the component suffix) and add `print:hidden` to sidebar/header shells. | S | [MDN print CSS](https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_media_queries/Printing) |
| R2-006 | Unit show, land-parcel show/create | owner | 1280 | Breadcrumb | Non-final crumbs declared without `href` (`{title:'Units'}`, `{title:'Land parcels'}`) render as links pointing at the *current* page (verified: crumb URL = page URL). A link that goes nowhere erodes trust in navigation. | `R2-024-landparcel-show-1280.png` | `resources/js/components/breadcrumbs.tsx:29-39` (render `<BreadcrumbPage>`-style span when `href` is absent), `resources/js/pages/organisation/unit/show.tsx:129-131`, `.../land-parcels/show.tsx:803-810`, `.../land-parcels/create.tsx:53-62` | Medium | Single guard in `Breadcrumbs` + add missing `href`s. | S | [Nielsen Norman: breadcrumbs](https://www.nngroup.com/articles/breadcrumbs/) |
| R2-007 | Reports, inspections, payments + forms, agreement templates, meter readings, assistant, drafting, AI settings, expense/payment edits | all tenant roles | 1280 | Consistency | No breadcrumb trail on ~15 pages (no `.layout = {breadcrumbs}`), while sibling pages (expenses, maintenance, units, staff, audit) have one. Users lose orientation exactly in the deepest flows (create/edit). | `R2-016-payments-1280.png`, `R2-020-payment-create-1280.png` | Each page component (cf. `.../expenses/index.tsx:218-220` for the pattern) | Medium | Add `.layout` breadcrumbs to every tenant page; lint for pages missing it. | M | — |
| R2-008 | Units table/show, platform expenses, occupant rentals, payment receipt | owner, occupant, admin | 1280 | Number format | Money rendered four ways: raw `25000.00` (units table + unit show rent/deposit), mixed decimals `KES 5,640.5` / `KES 15,963.07` (platform expenses), 2-decimal `KES 180,833.33` next to 0-decimal `KES 25,000` (occupant rentals). Leases/receipts use `KES 25,000`. | `R2-018-units-1280.png`, `R2-030-platform-expenses-decimals.png`, `R2-032-occupant-rentals-1280.png` | `.../unit/index.tsx:248-254`, platform expenses table, searcher rentals page | Medium | One `formatKES()` helper (0 decimals for whole amounts) used everywhere; see §6 tokens. | M | [Apple HIG: values](https://developer.apple.com/design/human-interface-guidelines/values) |
| R2-009 | Payments table, tenant audit show, platform audit | owner | 1280 | Date format | Three date dialects: ISO `2026-11-01` (payments/expenses tables), US-locale `11/11/2026, 11:39:50 PM` via bare `.toLocaleString()` (tenant audit show), `11 Nov 2026, 23:28` (platform audit). | `R2-028-payments-withrow-1280.png`, `R2-026-audit-show-dateformat.png` | `.../audit/show.tsx:54-59`, payments/expenses tables | Medium | One `formatDateTime()` with `en-GB` day-month-year; never bare `toLocaleString()`. | S | [Material 3: date formats](https://m3.material.io/styles/typography/applying-type) |
| R2-010 | Receipt, payments table, property dashboard, land-parcel show | owner | 1280/360 | Microcopy / casing | Status/enum casing inconsistent: `received` (receipt) vs `Received` (payments table); `active` chip on property dashboard vs `Occupied`/`Vacant` in units; raw lowercase `residential`/`active` on land-parcel show. | `R2-027-payment-receipt-1280.png`, `R2-035-prop-dashboard-360.png`, `R2-024-landparcel-show-1280.png` | Receipt page, `status-cell.tsx` (use it everywhere), land-parcel show | Medium | Route all statuses through `StatusCell`/capitalized labels. | S | [Material 3: capitalization](https://m3.material.io/foundations/content-design/style-guide) |
| R2-011 | Payments table row | owner | 1280 | Table / a11y | TENANT cell renders empty (no `—` placeholder) when the payment has no tenant, and the edit link reads `Edit payment for null` (null interpolated into the aria-label). | `R2-028-payments-withrow-1280.png` | `.../payments/index.tsx` (row + aria-label) | Medium | Placeholder + label built from property/unit fallback. | S | — |
| R2-012 | `/payments/{id}/receipt` | owner | 1280 | Microcopy | Title and h1 render `Receipt — —` / `Rent receipt — —` when tenant name is absent; `Tenant —` row. Double-dash headings look broken. | `R2-027-payment-receipt-1280.png` | `.../payments/receipt.tsx` | Medium | Fall back to property + unit (e.g. `Rent receipt — A1`) and hide the tenant row when unknown. | S | — |
| R2-013 | `/assistant` (unconfigured), `/platform/assistant` | owner, admin | 1280 | Headings / a11y | No `h1` on either assistant page (tenant unconfigured state has no headings at all; platform page has only an `h2`). Screen-reader and agent users get no page title landmark. | `R2-019-assistant-unconfigured.png` | `.../assistant/index.tsx`, `.../platform/assistant` page | Medium | Add `h1` (+ keep the helpful unconfigured guidance). | S | [WCAG 2.4.6](https://www.w3.org/WAI/WCAG21/Understanding/headings-and-labels.html) |
| R2-014 | `/settings/personal-ai` | owner | 1280 | Headings / a11y | Two `h2`s (`Settings`, `Personal AI settings`), no `h1` — heading order starts at level 2 (sibling settings pages have proper `h1`s). | — (snapshot) | `.../settings/personal-ai` page | Low | Promote page title to `h1`. | S | [WCAG 1.3.1](https://www.w3.org/WAI/WCAG21/Understanding/info-and-relationships.html) |
| R2-015 | Central dashboard tabs (+ anywhere `HomeDashboardTabs` renders) | all | 1280 | Accessibility | Tab `value`s are URL paths (`/dashboard`, `/home`), so Radix emits `aria-controls="…-content-/dashboard"` — invalid ARIA (Lighthouse `aria-valid-attr-value` fail, also flags agentic-browsing tree). | Lighthouse `report.json` | `resources/js/components/home-dashboard-tabs.tsx:25-31` | Medium | Use plain values (`home`/`dashboard`), map to URLs in `onValueChange`. | S | [Radix Tabs docs](https://www.radix-ui.com/primitives/docs/components/tabs) |
| R2-016 | Global search trigger (every app page) | all | 1280 | Accessibility | Visible label `⌘K` does not match accessible name `Search (Command K)` (Lighthouse `label-content-name-mismatch`). Voice-control users saying "click Search" fail. | Lighthouse `report.json` | `resources/js/components/global-search.tsx:232` | Low | Include visible text in the name, e.g. `aria-label="Search ⌘K"`, or render visible "Search" text. | S | [WCAG 2.5.3](https://www.w3.org/WAI/WCAG21/Understanding/label-in-name.html) |
| R2-017 | Landing `/` | guest | 1920 | Accessibility | Document has no `<main>` landmark (Lighthouse fail, carried over). One-line fix in the landing layout. | Lighthouse `report.json` | Landing page (`welcome`) | Low | Wrap content in `<main id="main">`. | S | [WCAG 1.3.1](https://www.w3.org/WAI/WCAG21/Understanding/info-and-relationships.html) |
| R2-018 | `favicon.ico` (all pages) | all | all | Network | `/favicon.ico` returns the icon bytes with HTTP 404 status — console error on every page load (only console error found all round). `public/favicon.ico` exists; looks like a web-server/fallback misconfiguration. | Console messages (landing + occupant sessions) | Server config / `public/` routing | Nit | Serve the existing file with 200 (or alias to `favicon.svg`). | S | — |
| R2-019 | `/suspended` | owner | 1280 | Microcopy | Page description duplicated: "Contact your administrator if you believe this is an error." appears as both lead text and body sentence. | `R2-034-suspended-1280.png` | `.../auth/suspended.tsx` | Nit | Keep one; make the body specific (who the administrator is / what to do). | S | — |
| R2-020 | `/expenses` (none deletable in UI) | owner | 1280 | Destructive actions | `DELETE /expenses/{expense}` exists but no UI affordance deletes an expense (index has edit links only; edit page has no Delete). Miscategorized/duplicate expenses are permanent from the user's perspective. | `R2-042-expense-row-1280.png` | `.../expenses/index.tsx` (add confirm-delete affordance) | Low | Add row delete with the standard confirm modal. | S | [Nielsen Norman: error recovery](https://www.nngroup.com/articles/slips/) |
| R2-021 | Payment create validation | owner | 1280 | Microcopy | Empty submit yields `The lease id field is required.` — raw snake-case attribute leaks into user-facing copy. | `R2-038-payment-validation-1280.png` | Form request `attributes()` (payment store request) | Nit | Map `lease_id → Lease`, audit other `attributes()` gaps. | S | — |
| R2-022 | Land-parcel show | owner | 1280 | Headings | `h1` jumps straight to `h3` (`Sections`, `Managers`, no `h2`). | `R2-024-landparcel-show-1280.png` | `.../land-parcels/show.tsx` | Nit | Demote section titles to `h2` (or nest under an `h2`). | S | [WCAG 1.3.1](https://www.w3.org/WAI/WCAG21/Understanding/info-and-relationships.html) |
| R2-023 | Report print | owner | 1280 | Consistency | `h1` and `h2` both read `Occupancy report` back-to-back (plus the same duplication pattern on other print views). | `R2-023-report-print-1280.png` | `.../reports/print.tsx` | Nit | Single title; make the second line org + range only. | S | — |
| R2-024 | Guest home filters dialog | occupant | 1280 | Microcopy | Dialog description reads `No extra filters` while the form contains Min/Max rent + Sort. | `R2-039-guesthome-filters-1280.png` | Guest home filters dialog | Nit | Describe the actual filters (`Filter by rent and sort order`). | S | — |
| R2-025 | Lease table row actions | owner | 1280 | Touch targets | Row icon buttons measure 36×36 (checkbox 16×16) — below the 44px Apple HIG target. Passes WCAG 2.2 AA (24px min) so this is a nit, worth fixing when tables are next touched. | Measured via `getBoundingClientRect` | Shared table action buttons | Nit | Pad icon buttons to 44px on touch layouts. | S | [Apple HIG: controls](https://developer.apple.com/design/human-interface-guidelines/buttons) |
| R2-026 | Landing tabpanel (`Questions, answered` region) | guest | 1920 | Accessibility | `<ul role="tabpanel">` with `<li>` children (invalid: tabpanel must not be a list parent; flagged under agentic-browsing/listitem). Noted during Lighthouse pass. | Lighthouse `report.json` | Landing FAQ/pricing tabs | Low | Use `role="tablist/tab/tabpanel"` correctly or plain list markup. | S | [WAI-ARIA tabs pattern](https://www.w3.org/WAI/ARIA/apg/patterns/tabs/) |
| R2-027 | `/properties/create` (Free plan) | owner | 1280 | Verified OK | Plan gate renders correctly ("Your current plan does not allow adding more properties. Upgrade in Billing") — no issue, recorded as checked. | — | — | — | — | — | — |
| R2-028 | Emails (OTP, rent/billing reminders, digest) | n/a | n/a | Verified OK (code-level) | Inline-styled, explicit light color-scheme, 480px max-width, mono code block, text-part mirrors. No footer/contact block (acceptable for transactional). Digest uses 2-decimal KES consistently. | — | `resources/views/email/*`, `resources/views/mail/org-digest.blade.php` | — | Consider a footer with support contact when volume grows. | — | — |

## 5. Cross-cutting patterns (one systemic fix each)

1. **Money/dates/statuses formatted ad hoc per page** (R2-008/009/010). Fix:
   add `formatKES()`, `formatDate()`/`formatDateTime()` (`en-GB`, Africa/Nairobi),
   and route every status through `StatusCell` — then grep-ban bare
   `toLocaleString()` and raw `monthly_rent` interpolation.
2. **Breadcrumb coverage is opt-in and half the app opted out** (R2-007) and
   **crumbs without `href` self-link** (R2-006). Fix: guard in `Breadcrumbs`
   (non-final without `href` renders as text) + require `.layout.breadcrumbs`
   on every tenant/platform page (a lint rule or a layout default derived from
   the route name).
3. **Sidebar gates and route gates are two independent implementations**
   (R2-003, R2-004). Fix: derive both from one capability map (the sidebar's
   `canView*` set and the `sub-permission:*` middleware should read the same
   source; owner-only controller extras like `authorizePropertyAccess` must be
   reflected in nav or removed).
4. **Print is styled per call-site but the shell ignores print** (R2-005). Fix:
   correct the `app.tsx` layout exemptions and add `print:hidden` to the
   sidebar/header shells once, instead of at every print page.
5. **Radix/ARIA values derived from URLs and visible-text/name mismatches**
   (R2-015/016/026). Fix: never use paths as component values; audit
   `aria-label`s against visible text app-wide (Lighthouse CI would catch all
   three).

## 6. Suggested tokens / component standards (observed, not invented)

- `formatKES(amount)`: `KES 25,000` (0 decimals when whole, else up to 2) —
  matches leases/receipts, the dominant convention.
- `formatDate`: `11 Nov 2026`; `formatDateTime`: `11 Nov 2026, 23:28` (EAT) —
  matches the platform audit index.
- Status: capitalized label + `StatusCell` chip everywhere (`Active`,
  `Occupied`, `Received`); never raw enum strings or lowercase chips.
- Tables: uppercase section headers are mixed (`Unit` vs `PROPERTY` in units) —
  standardize on the uppercase-DataTable convention; empty cells always `—`;
  aria-labels built from visible fallbacks, never `…for ${nullable}`.
- Validation messages use display names (`Lease`, not `lease id`) via FormRequest
  `attributes()`.
- Every tenant/platform page sets `.layout = { breadcrumbs: [...] }` with real
  `href`s on non-final crumbs; dialogs keep `description` truthful.

## 7. Prioritized action plan

**Now (correctness) — DONE 2026-11-12:** R2-001 (deposit-refunds 500), R2-002 (parcel edit 500),
R2-005 (print shell), R2-006 (breadcrumb guard), R2-007 (missing breadcrumbs
batch), R2-015 (tab values).
**Next (trust) — DONE 2026-11-12:** R2-003 + R2-004 (assigned-property scoping
enforced: route re-gating, delegation-aware lease gate, list/record scoping,
sidebar/widget alignment), R2-008/009/010 (format
helpers + rollout), R2-011/012 (payments placeholders/titles), R2-013/014
(headings), R2-016 (search label), R2-017 (landing `<main>`), R2-020
(validation names).
**Later (polish) — DONE 2026-11-13, except R2-018 (environmental, see fix log):** R2-019 (suspended copy), R2-021 (parcel heading order),
R2-022 (print titles), R2-023 (filters description), R2-024 (44px targets),
R2-026 (tabpanel roles). R2-018 (favicon serving) needs no in-repo change.

## 8. Open questions / not verified

- Why does `maintenance.manage` (granted to caretaker sub-role 5, confirmed via
  tinker) still 403 on `GET /maintenance`? Same-method check passes in tinker
  but the Inertia visit bounces — needs backend debugging (session org vs
  `TenancyContext`, `has-property` interplay). Recorded as R2-003 as observed.
- Inspection detail/report with real photo data (no inspections exist; creation
  needs uploads + AI) — `show.tsx` follows the same app-chrome pattern as other
  print views, untested live.
- Branded 403/429/500 pages exist in code but `APP_DEBUG=true` locally renders
  exception pages instead; verify on a staging build with debug off.
- Admin impersonation button deliberately not clicked (would hijack sessions).
- `/setup/first-asset` and `/setup/team` as a genuinely new user (no fresh
  account created; existing accounts redirect past early funnel steps).
- Logged-out public listings don't exist (`/home` requires auth) — the only
  public surface is the landing page; `listings/{unit}` 404s for unlisted units
  (verified correct) but a *listed* unit page was never rendered (no public
  listings in seed data).
- Long-text truncation (very long names/descriptions) and hover/focus sweeps
  were sampled, not exhaustive.
