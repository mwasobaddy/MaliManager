# MaliManager Table UI — Reference Pattern

> Living notes from rebuilding the **Organizations** table (`resources/js/pages/organizations/index.tsx`).
> This page is the reference implementation. Other tables (Users, Plans, Expenses,
> Subscription payments, tenant tables) should be migrated to match it.
> Visual language is defined in `ui-design-system.md` — this file covers *structure*.

---

## 1. Anatomy

```
┌────────────────────────────────────────────────────────────────┐
│ Heading + description                        [+ New org]       │  ← page CTA
│ ┌────────────────────────────────────────────────────────────┐ │
│ │ [🔍 live search]                      [⚙ Filters] [Export] │ │  ← card header band
│ ├────────────────────────────────────────────────────────────┤ │  ← border-strong
│ │ Organization ↕ │ Plan │ DOMAIN │ Members ↕ │ Status ↕ │ ACTIONS │
│ │ name + slug    │ Free │ …      │ 6        │ ◉        │ 👁 ↩ ✎ 🗑 │
│ └────────────────────────────────────────────────────────────┘ │
│ Page 1 of 3 (25 organizations)          [Previous] [Next]      │
└────────────────────────────────────────────────────────────────┘
```

**One card, not two.** Search/filters live in a header band *inside* the table's
own card (`border-b`), with the table flush to the card edges. Two stacked cards
(a floating toolbar card above a table card) is the "box-in-box" that
Material/Supernova/Vendure all warn against — the card should be the table's frame.

Research behind that decision:
- **Material Design (Data tables):** "Data tables may be embedded on a surface,
  such as a card… they can include the ability to query and manipulate data."
- **Supernova:** "Place search and filters on the left where they directly narrow
  the data. Align toolbar actions to the right where they relate to the table as a
  whole (export, column toggle)." And do NOT put the toolbar below the table.
- **Vendure design issue #45** documents fixing exactly this box-in-box by making
  "the card become the table's frame" (header band / flush body / footer band).

### Where page actions live

| Action | Home | Why |
|---|---|---|
| **New organization** | Page header | Page-level CTA, creates a resource |
| **Export** | Card header band, right | Acts on *this table's result set* |
| **Filters** | Card header band | Narrows the data |

Search/filters (narrowing) are never mixed with page CTAs (creating).

Overlays triggered from the toolbar:
- **View** → right-side `Sheet` on large screens, centred `Dialog` on small (see §6)
- **Filters** → right-side `Sheet` on large screens, centred `Dialog` on small

Status is a control, not a badge: the status icon itself toggles
suspend/reactivate and is labelled `Suspend <name>` / `Reactivate <name>`.

---

## 2. Row actions — `ActionIconButton`

`resources/js/components/action-icon-button.tsx`. Icon-only, shadcn `Tooltip` on hover
(global `TooltipProvider`, `delayDuration={0}`), `size="icon"` from the shadcn `Button`.

```tsx
// plain action
<ActionIconButton icon={<Eye />} label="View Acme" color="view" onClick={...} />

// navigation — asChild, and OMIT icon (the child carries the content)
<ActionIconButton asChild label="Edit Acme" color="edit">
    <Link href={routes.edit(org.id)}><Pencil /></Link>
</ActionIconButton>

// destructive submit — wrap in Inertia <Form>, NOT Form as="button".
// Only valid when the Form owns a bound input; see §10 trap 1.
<Form method="post" action={routes.destroy(org.id)} className="inline">
    <ActionIconButton type="submit" icon={<Trash2 />} color="danger" label="Delete Acme" />
</Form>

// argument-only action (no bound input) — use router directly, NOT <Form>:
router.patch(routes.status(org.id), { status: 'suspended' }, { preserveScroll: true })
```

| Color | Token | Typical use |
|---|---|---|
| `view` | `--action-view` | View / open / preview |
| `edit` | `--action-edit` | Edit |
| `danger` | `--action-danger` | Delete / destructive |
| `brand` | `--brand-primary` | Terracotta — impersonate / special |
| `accent` | `--brand-accent` | Teal — secondary special |
| `primary` | `--primary` | Main CTA |
| `default` | `--muted` | Neutral / unclassified |

**Rules that must not be broken:**
- Backgrounds are `/12` tint, `/20` on hover. Never solid fills in a table.
- **Never add `dark:` variants to the colour classes.** The tokens already hold separate
  `:root` / `.dark` values and `@theme` maps `--color-x: var(--x)`, so the token swaps
  itself. Adding `dark:` double-applies and breaks the swap.
- `label` doubles as the `aria-label`, so include the row's name
  (`label="Delete Acme"`, not `label="Delete"`) — otherwise screen-reader users cannot
  tell which row they are on.
- `Form as="button"` nests a `<button>` inside a `<button>`. Use `<Form className="inline">`
  plus `type="submit"`.

Verified both schemes: light `#2b6cb0/#c87a53/#276749/#c53030`,
dark `#5b9bd5/#e2956c/#4fa877/#e57373`.

---

## 3. Shared components

| Component | Purpose |
|---|---|
| `resources/js/components/action-icon-button.tsx` | Icon-only row action + tooltip (§2) |
| `resources/js/components/data-table.tsx` | TanStack Table wrapper, **server-side** sorting |
| `resources/js/components/responsive-overlay.tsx` | Sheet on desktop / Dialog on mobile |
| `resources/js/components/status-cell.tsx` | Status icon + tone mapping |

### DataTable

`@tanstack/react-table` is pinned to **v8** (`^8.21.3`). The v9 line is a full
rewrite — `useReactTable` only exists there under the `/legacy` subpath — so v8 was
chosen as the stable target six tables will depend on.

`manualSorting: true`: the table renders `getAllRowModel()` over the **current page
only**, so a 25k-row table costs the same as a 25-row one. `onSortingChange` writes
`?sort=<column>&direction=<asc|desc>` and the server sorts.

Header markup follows `ui-design-system.md` §4.2: uppercase 12px muted labels,
`--border-strong` rule, `--brand-primary` icon on the active sort. Non-sortable
columns set `enableSorting: false`.

### ResponsiveOverlay

No new primitive was needed — shadcn `Sheet` already has the slide animation.
The sheet/dialog switch happens in JS via `useIsMobile()` because CSS cannot swap
two different Radix roots, and mounting both would trap focus twice.

---

## 4. Contrast — `--border-strong` and the transparent Input

shadcn's `Input` is `bg-transparent`. That is fine on the page background but on a
**card** surface it left the search field defined by its border alone:

| | Card bg | Input fill | Border vs card |
|---|---|---|---|
| Light (before) | `#FAF8F5` | transparent | `#E7E1D6` → **1.23:1** |
| Dark (before) | `#242220` | transparent | `#36322D` → **1.25:1** |
| Light (after) | `#FAF8F5` | `#F5F2EB` | `#D8CFBF` → **1.46:1** |
| Dark (after) | `#242220` | `#1A1917` | `#4A443C` → **1.65:1** |

`--border-strong` (`#D8CFBF` / `#4A443C`) was **specified in `ui-design-system.md`
§2.1/§2.2 but never implemented** — it is referenced in eight places in that doc
(data table headers, checkboxes, pagination, scrollbars…) and was missing from
`app.css`. It is now defined and exposed as `--color-border-strong`.

So the search input uses `bg-background border-border-strong`: defined by fill
*and* border rather than border alone.

⚠️ **Honest limit:** WCAG 1.4.11 asks 3:1 for UI component boundaries and the warm
neutral palette cannot reach it on a card surface — the best available border is
1.65:1. Reaching 3:1 means darkening `--border-strong` substantially, which is a
palette decision, not a layout one. Raise it if you want true AA on the boundary.

---

## 5. Status display — icon + tooltip, not a badge

`ui-design-system.md` §1: *"never convey meaning by color alone — always pair with icon + label."*
The status column uses a coloured icon with a tooltip carrying the human-readable status,
rather than a filled badge pill.

| Status | Icon | Token |
|---|---|---|
| `active` | filled circle / check | `--status-success` |
| `suspended` | paused / alert triangle | `--status-warning` or `--status-danger` |

---

## 6. Overlays

The repo already has shadcn `Sheet` (`resources/js/components/ui/sheet.tsx`, Radix Dialog
with `side="right" | "left" | "top" | "bottom"` and slide animation) — **no new component
was needed**. Responsive behaviour (sheet on large, centred dialog on small) is switched in
JS via a media-query hook, because Radix primitives cannot be swapped by CSS alone.

### The URL-driven modal bug (fixed)

**Symptom:** clicking *View* updated the URL to `?organization=5` but no modal appeared
until a manual reload.

**Root cause:** the component derived modal state from `window.location.search` *during
render*. Inertia's `router.get()` pushes history **after** React has rendered the new
props, and `window.location` is not React state — so nothing re-triggered a render with the
new URL. A full page load re-mounted the component and read the URL correctly.

**Fix:** derive from Inertia's reactive `usePage().url` instead of `window.location`.
There is no `useSearchParams` in the installed Inertia version, so `usePage().url` is the
reactive source of truth.

**Do not reintroduce** `new URLSearchParams(window.location.search)` in a component body.

---

## 7. Live search

Debounced so typing does not hammer the server. Target ≈350 ms.

```
keystroke ──▶ [debounce 350ms] ──▶ router.get(url, {search, ...}, {preserveState:true, replace:true})
```

Reset to page 1 on every new query, or a filtered result set can land on an empty page.

---

## 8. Organization status — lifecycle (now implemented)

**Status values:** `active` / `suspended`, now a real `App\Enums\OrganizationStatus`
enum cast on the model. All 5 existing rows were `active`, so the cast was safe.

**Suspension is now enforced** (it previously did nothing):

- `PATCH organizations/{organization}/status` → `OrganizationController::setStatus`,
  mirroring `users/{user}/status`.
- `OrganizationManagementService::setStatus()` drops every member's rows from
  `sessions`, so an open tab stops working immediately rather than at cookie expiry.
- New `EnsureOrganizationIsActive` middleware, alias `organization.active`, applied to
  both tenant route groups (`['auth','organization.active','has-property']` and the
  org-level `['auth','organization.active']`). Blocks with a toast.
- Impersonation refuses a suspended organization.

⚠️ **Testing gotcha:** stancl caches the resolved `Tenant` (and its loaded
`organization` relation) per PHP process. Flipping status *between two simulated
requests in one test* reads a stale model and looks like the middleware is broken.
Real requests resolve tenancy fresh. Tests must set the status **before** any request.

### Still true — deletion cascades to nothing

`Organization` uses `SoftDeletes`, so no FK cascade fires. Surviving a delete: the
`Tenant`, its `Domain`, all `organization_user` pivots, delegations, sub-roles,
properties, occupants, leases, payments and the on-disk `{slug}/` storage.

The delete dialog copy was **fixed** — it claimed "its tenant will no longer be
reachable", which was inaccurate.

---

## 9. Migration checklist for other tables

- [ ] Status filter inside the filters overlay
- [ ] Filters overlay = right-side sheet (large) / centred dialog (small)
- [ ] Row actions use `ActionIconButton` with the correct semantic colour
- [ ] Destructive actions submit via `<Form className="inline">` + `type="submit"`;
      argument-only actions use `router.patch(url, {data})` instead (see §10)
- [ ] Status column = icon + tooltip, never colour alone
- [ ] Header sortable where a meaningful server-side sort exists
- [ ] Row action `label` includes the row's human-readable name
- [ ] Live search debounced ~350 ms, resets to page 1
- [ ] Use `DataTable` with `manualSorting`; never sort a full dataset client-side
- [ ] Toolbar lives INSIDE the table card as a `border-b` header band — never a
      second stacked card (box-in-box)
- [ ] Search/filters left; table-level actions (export) right; page CTAs in the page header
- [ ] Inputs on card surfaces use `bg-background border-border-strong`, not bare `Input`
- [ ] Overlays use `ResponsiveOverlay`, not a bare `Dialog`
- [ ] URL-driven state reads `usePage().url`, never `window.location`

---

## 10. Traps found the hard way

1. **Inertia `<Form>` with no bound inputs submits an empty body.** It serialises only
   fields from its own `data` object. A toggle button wrapped in `<Form method="post">`
   sent `{}` and the server received no `status` — the request returned 303 and looked
   like it worked. Use `router.patch(url, { status })` for argument-only actions.
2. **`window.location` is not reactive under Inertia.** See §6.
3. **`php artisan wayfinder:generate` strips `.form()` helpers.** They are only emitted
   by the Vite plugin with `formVariants: true` (`vite.config.ts`). Re-run
   `npm run build` instead, or several pages break at once.
4. **TanStack Table v9 is a rewrite.** Pin v8 for the familiar API.
5. **React Compiler skips `useReactTable`** (`react-hooks/incompatible-library`). It is a
   warning only — `lint:check` has no `--max-warnings`, so CI still passes.
6. **A sub-permission gate can lock a role out of the page its own landing flow sends it
   to.** `property.manage` guards only `GET /properties` (the picker), but the Caretaker
   bundle did not ship it, so `AuthLanding` redirected caretakers to `/properties` and the
   `sub-permission` middleware bounced them straight back out — a silent dead end that
   surfaced only as a failing feature test. When adding a gate to a route, check which
   default bundles hold it. Full write-up in `project.md` §6.2.
7. **Bundle edits only reach new organizations.** `DefaultSubRoles::createFor()` runs at
   org creation; existing orgs keep their old grants. `DefaultSubRoles::syncBundlesFor()`
   (called from `RolesAndPermissionsSeeder`) repairs them additively.