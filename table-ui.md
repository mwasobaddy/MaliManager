# MaliManager Table UI — Reference Pattern

> Living notes from rebuilding the **Organizations** table (`resources/js/pages/organizations/index.tsx`).
> This page is the reference implementation. Other tables (Users, Plans, Expenses,
> Subscription payments, tenant tables) should be migrated to match it.
> Visual language is defined in `ui-design-system.md` — this file covers *structure*.

---

## 1. Anatomy

```
┌────────────────────────────────────────────────────────────────┐
│ Organization management      [⬆ New organization]            │  ← labelled CTA
│ Create and manage orgs…                                        │  ← description
│ ┌────────────────────────────────────────────────────────────┐ │
│ │ [🔍 live search]                    [⚙] [↑ Import] [↓ Exp] │ │  ← card header band
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
| **New organization** | Page header, as a `LabelledIconButton` | Page-level CTA; the permanent label under the icon is what distinguishes it from the utility icons |
| **Import / Export** | Card header band, icon + tooltip | Act on *this table's result set* |
| **Filters** | Card header band, icon + tooltip | Narrows the data |

Search/filters (narrowing) are never mixed with page CTAs (creating).

### Rows are the detail trigger

There is **no View button**. `DataTable` takes `onRowClick`, which opens the row's
sheet. Interactive children (the status toggle, and the whole action cell) call
`event.stopPropagation()` so they do not also open the sheet — the action cell is
already wrapped in one handler, and the status toggle stops it on its own.

### Alignment

`DataTable` reads `meta.align` per column (`left` | `right` | `center`), applied to
both `<th>` and `<td>`. TanStack's `ColumnMeta` is empty by default so `data-table.tsx`
augments it. Members is right-aligned with `tabular-nums`; Status is centred.

### Long values

Cells truncate (`truncate` + `max-w-*`) rather than wrapping, so row height stays
constant; the full value rides on `title` for hover and remains in the a11y tree.

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

#### Loading — `loading` swaps the body, not the table

`loading` replaces the `<tbody>` with skeleton rows and leaves the `<thead>` alone.
Hiding the header too would make the table collapse and re-expand, which reads as a
layout bug rather than as loading. The skeleton honours each column's `meta.align`,
so the bars line up under their real columns.

```tsx
<DataTable columns={columns} data={organizations.data} loading={isTableLoading} />
```

Row count defaults to the visible row count with a floor of **5**
(`loadingRows ?? Math.max(data.length, 5)`). Using the previous count alone looks
right on a page change but collapses to a single grey line when a filter narrows 20
rows to 1, so the table jumps twice. Pass `loadingRows` to override — usually with
the page size, when that is known.

**Row height is measured, not guessed.** A real row here is 53px because the
primary cell stacks a title and a subtitle; a single-line table would be 36px. A
constant is wrong on at least one table, and a single `h-4` bar gives a ~32px row
that reads as *thin* rather than as loading. So `DataTable` measures the first real
row in a layout effect whenever it is not loading and pins the skeleton rows to
that height. Before the first measurement lands (no rows rendered yet) the primary
cell falls back to two stacked bars mirroring the title + subtitle shape.

Drive it from `onFinish` on the request that replaces the rows, not from a global
"is navigating" flag:

```tsx
function refetchTable(params: Record<string, string | number>, options?: { replace?: boolean }) {
    setIsTableLoading(true);

    router.get(orgRoutes.index().url, params, {
        preserveState: true,
        replace: options?.replace ?? false,
        only: ['organizations', 'filters', 'sort'],
        onFinish: () => setIsTableLoading(false),
    });
}
```

`onFinish` fires on failure too, so the skeleton cannot get stuck. A global
navigation flag is wrong here for two reasons: it would also cover sheet
open/close, which change no data and are instant, and it needs to distinguish
navigation from form submissions, which have their own busy state.

### ResponsiveOverlay

No new primitive was needed — shadcn `Sheet` already has the slide animation.
The sheet/dialog switch happens in JS via `useIsMobile()` because CSS cannot swap
two different Radix roots, and mounting both would trap focus twice.

The action row is **not** taken from the footer primitives. shadcn's own
`SheetFooter` is `flex flex-col`, which stretches each button to the panel's full
width; `DialogFooter` is `flex-col-reverse` until `sm`. Neither gives the
right-aligned row the design system asks for. Rather than patch the vendored
primitives — which the next `shadcn add` would silently revert — `OverlayFooter`
renders its own `flex w-full flex-wrap items-center justify-end gap-2`, so the row
lays out identically in both branches and wraps instead of stacking on a narrow
viewport. Colour roles live in `ui-design-system.md` §Action Row.

### One row sheet, three modes

A row never opens a stack of nested sheets. It opens **one** overlay driven by two
query params — `organization` (the row id) and `mode` (`view|edit|delete`):

| Mode | Desktop | Mobile |
|---|---|---|
| `view` | Right-side Sheet | Centred Dialog |
| `edit` | Right-side Sheet | **Full page** (`/organizations/{id}/edit`) |
| `delete` | Right-side Sheet | **Centred Dialog** (password confirm) |
| `create` | Right-side Sheet | **Full page** (`/organizations/create`) |

Only **Edit** and **Create** fall back to a full page on small screens: they are
long forms that are cramped in a dialog. View and Delete are short and stay as
overlays.

The reason View uses `replace` is that the row click is the *default* meaning of
that URL. Opening Edit/Delete pushes an entry, so Back returns to View, then the
row.

```ts
if (isMobile && mode === 'edit') {
    router.visit(fallbackUrl); // RouteDefinition → visit needs the href

    return;
}

setOverlayParam('mode', mode, {
    replace: mode === 'view',
    keep: { organization: String(organization.id) },
});
```

Do not send mobile to the index for `delete` — that silently navigates away and
performs nothing, which is exactly the bug the mode guard above prevents.

### Overlays close on `onSuccess`, never `onSubmit`

Every form inside an overlay closes it from `onSuccess`:

```tsx
<Form
    id="organization-edit-form"
    method="patch"
    action={orgRoutes.update(id)}
    onSuccess={() => closeRowSheet()}   // ✅
    // onSubmit={() => closeRowSheet()} // ❌ closes before the response
>
```

`onSubmit` fires the instant the button is pressed. The sheet then tears down the
form and drops the URL params **before** the server has replied, so when the
response finally comes back with validation errors there is no overlay left to
render them in — the user is left on the bare table having "failed" a save they
never got to make. This silently affects Create, Edit, Delete *and* the CSV
import sheet, since `mimes`/`max` rejections arrive the same way.

Rule of thumb: if the overlay has any server-side validation, it closes on
`onSuccess`.

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

## 5. Status display — bare icon, no chip

`ui-design-system.md` §1: *"never convey meaning by color alone — always pair with icon + label."*
The status column is a **coloured glyph with no background tint** — a filled circle
behind every row reads as a miniature badge and adds noise to a dense table. The icon
shape carries the meaning; the tooltip carries the word.

| Status | Icon | Token |
|---|---|---|
| `active` | `CircleCheck` | `--status-success` |
| `suspended` | `TriangleAlert` | `--status-danger` |

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
- [ ] Detail sheet opens from the row (`onRowClick`); no View button; action cell stops propagation
- [ ] Columns that hold numbers use `meta: { align: 'right' }`; status uses `center`
- [ ] Long cell values truncate + carry `title`, never wrap
- [ ] Status is a bare glyph, not a tinted chip
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
8. **`query.has('key')` is true for an empty value.** Closing a URL-driven overlay
   navigates to `?key=` (present but empty), so a `has()` check never clears. Compare
   against `''`, or mirror the `Number(...) > 0` pattern used for id params.
9. **A cast value can break CSV output silently-ish.** `Organization.status` is an enum;
   `fputcsv` threw `Object of class OrganizationStatus could not be converted to string`.
   Any new cast that reaches a string sink (CSV, mail, PDF) needs `->value`.
10. **Exports must be a real navigation.** A `fetch()`-built blob URL would drop the
    session cookie; submit a hidden GET form instead. The organizations page does this in
    `downloadExportUrl()`.
11. **A "responsive" overlay handler that redirects *everything* on mobile silently kills
    the secondary flows.** `if (isMobile) router.visit(fallback)` made Delete navigate to
    the index and do nothing, because Delete has no page to visit. Branch on the mode, not
    on the viewport alone. See §3.
12. **Closing an overlay from `onSubmit` throws away the validation response.** The sheet
    unmounts before the server replies, so field errors render nowhere. Use `onSuccess`.
    See §3.
13. **A dropdown prop that nobody is looking at still costs a query per keystroke.** The
    owner picker is the only consumer of the full user list, but it was an eager prop, so
    every debounced search, sort, page change and sheet open re-queried and re-serialised
    all ~80 users. Wrapping it in `Inertia::optional()` dropped it from the initial payload
    entirely; the page now asks for it once, when the sheet opens, and derives its loading
    state from the prop being absent rather than tracking a second flag.
14. **A page usually needs two navigation helpers, not one.** Opening a sheet changes only
    the URL; a search changes the rows. They look identical at the call site but have very
    different costs — one asks the server to resend props the page already has, the other
    should show a skeleton. Splitting them keeps each honest.