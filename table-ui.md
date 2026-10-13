# MaliManager Table UI — Reference Pattern

> Living notes from rebuilding the **Organizations** table (`resources/js/pages/organizations/index.tsx`).
> This page is the reference implementation. **Users** (`pages/users/index.tsx`) and
> Every platform table has been migrated to it — **Plans**,
> **Subscription payments**, **Expenses** and **Audit** — as has **Users**. Only
> the tenant tables (properties, staff, occupants, land-parcels, drafting) have not.
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

### Form controls — one recipe for Input, Select and Textarea

A form should read as one row of controls, so the three share a recipe rather than each
page improvising: same border, radius, transparent surface, `shadow-xs`, `px-3`,
focus ring at `border-ring` + `ring-ring/50` + `ring-[3px]`, `aria-invalid` mapped to
destructive, and the same disabled treatment. Height is the only difference — `h-9` for
Input and Select, `min-h-16` with `py-2` for Textarea.

Two details are load-bearing rather than stylistic:

- **`text-base md:text-sm` on every control.** Below the `md` breakpoint the control is
  16px. iOS Safari zooms the viewport when it focuses an input under 16px and does not
  zoom back until the field loses focus, which strands users mid-form. This has to hold on
  Select and Textarea too, or they become the one control on the page that breaks it.
- **`w-full`.** Not one of the 52 `SelectTrigger` call sites passed `w-full`, so every
  Select in the app hugged its content while the Input above it filled the row.

Use the primitives. There are no native `<select>` or `<textarea>` elements left in
`resources/js`; the last 21 selects were hand-styled to imitate the component, which is
how four slightly different border/padding/focus recipes got into circulation in the first
place.

Two things to know when converting:

- Radix renders a **visually hidden native `<select>`** carrying `name`, so Inertia's
  `<Form>` still serialises a converted Select. Verify it rather than assuming: check that
  `form.querySelectorAll('select')` includes the field with its options.
- Radix reserves `value=""` for the placeholder and will not select it as an item. A native
  `<option value="">Unspecified</option>` becomes a `SelectValue placeholder` instead.

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
| `view` | Right-side Sheet | Bottom drawer |
| `edit` | Right-side Sheet | Bottom drawer |
| `delete` | Right-side Sheet | Bottom drawer |
| `create` | Right-side Sheet | Bottom drawer |

**Every mode is available on every viewport.** `ResponsiveOverlay` picks the
surface — a side `Sheet` on larger screens, a bottom `Drawer` on phones — and
nothing redirects to a separate page. `openRow()` and `openCreateSheet()` have no
`isMobile` branch at all; the same URL opens the same overlay everywhere.

This was a deliberate reversal. The research on this split: NN/g is blunt that a
bottom sheet should not replace page-to-page flows — *"a bottom sheet is a
transient UI element that is not intended to be a stable place for users to return
to"* — and Apple says *"for complex or prolonged user flows, consider alternatives
to sheets."* Both are about sheets being navigated *through* and about long
multi-step tasks. These overlays are neither: each is short, terminal and
single-screen.

Measured on a 390x844 phone, the largest of them (create, 6 fields) is **588px
tall with no internal overflow** against an 844px viewport:

| Overlay | Height | Internal overflow |
|---|---|---|
| Create | 588px | 0 |
| View | 428px | 0 |
| Delete | ~470px | 0 |
| Import | 370px | 0 |
| Export | ~350px | 0 |
| Filters | 268px | 0 |

So the form-goes-full-page rule was written against the wrong page. Keep it for
genuinely long forms, but re-measure before assuming a short one needs a page.

**Dismissal is never gesture-only.** `vaul` gives drag-to-dismiss, but Material,
NN/g and the accessibility guidance all require a visible alternative — swipe is
ambiguous (it can open the notification drawer) and unavailable to anyone who
cannot perform a precise vertical drag. Every drawer therefore carries an explicit
close button as well as Cancel. `DrawerHeader` centres its text via a
`group-data-…:text-center` variant that `tailwind-merge` will not override, so
the left alignment needs `!`.

The reason View uses `replace` is that the row click is the *default* meaning of
that URL. Opening Edit/Delete pushes an entry, so Back returns to View, then the
row.

```ts
function openRow(organization: OrganizationRow, mode: RowMode) {
    setOverlayParam('mode', mode, {
        replace: mode === 'view',
        keep: { organization: String(organization.id) },
    });
}
```

`DrawerContent` caps at `max-h-[80vh]` and its body scrolls. That matters once
the soft keyboard is open: it removes roughly 300px of usable viewport, so create
*does* overflow there even though it does not at rest. A sheet that fits at rest
is not automatically a sheet that fits while typing.

### Known limitation — overlays only resolve rows on the current page

`viewedOrganization` is `organizations.data.find(...)`, so
`/organizations?mode=view&organization=42` renders the "not on the current page of
results" state whenever row 42 is not in the current result set. That is invisible
while browsing (you always tap a row you can see) but breaks **deep links**, and
it is why the legacy `organizations/{id}/edit` page still exists as the only URL
that can open an off-page row today.

The fix is to resolve the row server-side when the URL asks for one the page does
not have, rather than to keep a second copy of the form alive for it.

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

## 5. Status display — bare icon in the cell, Power toggle in the action cell

`ui-design-system.md` §1: *"never convey meaning by color alone — always pair with icon + label."*
The status column is a **coloured glyph with no background tint** — a filled circle behind
every row reads as a miniature badge and adds noise to a dense table.

| Status | Icon | Token |
|---|---|---|
| `active` | `CircleCheck` | `--status-success` |
| `suspended` | `TriangleAlert` | `--status-danger` |

Status used to be a toggle button in the action cell. That was wrong for two reasons: the
status *readout* is not an action, and a text button that reads "active" next to a row that
is already labelled "active" gave the same word two meanings. Now:

- **Status column** — `StatusIcon`: display-only, colour + shape + tooltip.
- **Action cell** — `StatusPowerToggle`: a `Power` icon while the row is active (click to
  suspend) and a `PowerOff` icon while it is not (click to reactivate). Green for active,
  red for suspended, matching the action it performs rather than the state it sits in.

Both organizations and users use the same pair, so the two tables stay symmetrical.

### Teal needs a foreground token, not `text-white`

`--brand-accent` is `#0e7c7e` in light mode and `#2bb3ac` in dark mode. The dark value is a
**light** teal, so a hard-coded `text-white` lands at roughly 2.1:1 — far under AA. The
fill now pairs with `--brand-accent-foreground`, which is `#f2fbfb` light and `#0c2426`
dark, exactly as `--primary-foreground` already did. Any new solid brand fill must use the
matching `*-foreground` token rather than a literal colour.

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
- [ ] Destructive **forms inside an overlay** submit via Inertia `<Form>` with
      `method="delete"`; only row-cell buttons go through `ActionIconButton`

---

### Migrated tables

| Table | Page | Overlays | Notes |
|---|---|---|---|
| Organizations | `pages/organizations/index.tsx` | view / edit / delete / create / filters / import / export | The reference |
| Users | `pages/users/index.tsx` | same as organizations | Radio filters for status + role |
| Plans | `pages/platform/plans/index.tsx` | view / edit / delete / confirm-delete / create / filters | No import/export; status is a boolean |
| Subscription payments | `pages/platform/subscription-payments/index.tsx` | view / edit / delete / confirm-delete / create / filters | No status; optional org/plan lists; date-range filter |
| Expenses | `pages/platform/expenses/index.tsx` | view / edit / delete / confirm-delete / create / filters | No status; category is a closed set, so a radio filter |
| Audit | `pages/platform/audit/index.tsx` | view / filters / export | Read-only: no create, edit or actions column at all |

---

## 9a. Plans — the boolean-status variant

Plans differ from the other two in three ways worth knowing before copying the
pattern again:

1. **`is_active` is a boolean, not an enum.** There is no `PlanStatus`, so the
   controller takes `['required', 'boolean']` and the toggle sends `is_active`
   rather than a status string. Sorting allowlists the column directly.
2. **The default order is merchandising, not recency.** Plans list cheapest tier
   first (`sort_order`, then `name`). `direction` still defaults to `desc` for
   user-chosen sorts, so the *unsorted* branch must not apply it — otherwise
   clicking away from a sort silently reverses the default.
3. **Delete takes two steps inside one sheet.** The row Delete opens `mode=delete`
   which only states the consequence; the footer button moves to
   `mode=confirm-delete`, which is where the password prompt lives. That is a
   fourth `RowMode`, not a second overlay — stacking Radix modals fights over the
   focus trap and double-dims the page (same reason as §3).

**An inactive plan is not a broken one.** It is off sale. So the toggle passes
`inactiveTone="warning"` instead of the default `danger` that a suspended user gets.
`StatusPowerToggle` takes `activeTone`/`inactiveTone` for exactly this — the glyph
and tooltip stay the same, only the colour semantics change.

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
11. **Don't branch a navigation helper on the viewport — branch on nothing.** The
    original `if (isMobile) router.visit(fallback)` sent Delete to the index, where it did
    nothing, because Delete had no page to visit. The follow-up mistake was "fixing" that
    by adding a `mode === 'edit'` exception. The real answer was that the mode branch
    should not exist at all: the overlay already adapts via `ResponsiveOverlay`, so a
    separate page per viewport only ever creates a second copy of the form to keep in
    sync. See §3.
12. **Closing an overlay from `onSubmit` throws away the validation response.** The sheet
    unmounts before the server replies, so field errors render nowhere. Use `onSuccess`.
    See §3.
13. **A dropdown prop that nobody is looking at still costs a query per keystroke.** The
    owner picker is the only consumer of the full user list, but it was an eager prop, so
    every debounced search, sort, page change and sheet open re-queried and re-serialised
    all ~80 users. Wrapping it in `Inertia::optional()` dropped it from the initial payload
    entirely; the page now asks for it once, when the sheet opens, and derives its loading
    state from the prop being absent rather than tracking a second flag.
15. **`avoidCollisions={false}` on SelectContent silently destroys long option lists.** With
    it off, the list always opens *downward* and is clamped to the gap between the trigger
    and the viewport edge. In a bottom drawer the last field sits near the bottom of the
    screen, so a 76-option list was clamped to **62px — about two visible rows**, with the
    rest unreachable and no scrollbar to signal it. With collision avoidance on, Radix
    flips it upward: 718px, 21 visible options, scrollable. `SelectContent` must not carry
    `avoidCollisions={false}`.
16. **A Select's `defaultValue` is read once, on first mount.** The owner picker is behind
    an `Inertia::optional()` prop, so it first rendered with `owners === undefined` and
    locked in an empty default; when the list arrived the trigger still read "Loading
    owners…" with nothing selected. Render the Select only once its options exist and show a
    plain disabled control while loading, rather than mounting it empty and patching it later.

14. **A page usually needs two navigation helpers, not one.** Opening a sheet changes only
    the URL; a search changes the rows. They look identical at the call site but have very
    different costs — one asks the server to resend props the page already has, the other
    should show a skeleton. Splitting them keeps each honest.
17. **Radix `Select` cannot express "no role selected".** `SelectItem value=""` is reserved
    for the placeholder, so a filter offering *Any role* alongside four roles cannot be
    built from `Select` without fake sentinels. A `RadioGroup` has no such reservation and,
    with four options, is fewer taps than opening a dropdown. The users role filter and the
    role pickers in the create/edit sheets are all `RadioGroup` for this reason. The
    controlled value maps the empty filter onto an explicit `all` option rather than
    letting the group be uncontrolled and empty.
18. **`RadioGroup` serialises its value as a single comma-joined string.** A multi-select
    (platform roles) needs one `name` per checked value, which `RadioGroup` cannot do. The
    edit sheet therefore pairs the group with a hidden `roles[]` input carrying the
    comma-joined names, and the server splits on `,`. A hidden `roles[]` with an empty
    value is also what makes "no roles" submittable at all.
19. **A CSV export must reuse the table's own query builder.** `UserController::export()`
    originally ran `User::query()` with no filters, so "Export all" silently ignored the
    active search and status filter. Both controllers now share a private `filtered()`
    helper with `index()`; anything the table can filter by, the export can filter by.
20. **`scope=page` needs the server's page, not the table's.** The export helper reads
    `users.current_page` from the props rather than reaching into a TanStack table
    instance that the page does not hold — one source of truth for "what page am I on".
21. **Do not leave a dead status toggle behind.** `StatusToggleButton` was the old
    status-column affordance. Once `StatusIcon` became display-only and
    `StatusPowerToggle` took over the action cell, nothing referenced the old
    component — it was added and removed inside a single uncommitted branch, so
    `git log` never showed it and nothing failed to compile. Grep for a symbol
    before committing a rename, not only after.
22. **The shadcn `Checkbox` is a `<button>`, so it submits nothing.** It renders no
    hidden input, so a form built from `<Form>` plus `<Checkbox>` never sends `true`,
    and an unticked box never sends `false` either — leaving the old value in place on
    an update, which looks like the save silently failed. The plans form pairs each box
    with an explicit hidden input carrying `"1"`/`"0"`, which the `nullable|boolean`
    rule accepts. Any boolean that is not a radio needs this pairing.
23. **`features[]` cannot come from a `<textarea>`.** The plans request validates an
    array; the form edits it as one-per-line text. The textarea is therefore left
    *unnamed* and the split lines ride along as `features[]` hidden inputs — a named
    textarea would submit one string and fail the `array` rule. With an empty list
    nothing is sent, which is what `nullable` is for.
24. **`php artisan wayfinder:generate` silently drops `formVariants`.** That flag is
    set in `vite.config.ts` (`wayfinder({ formVariants: true })`), which the Artisan
    command does not read. Running it rewrites every generated route without the
    `.form` helper and `tsc` reports ~34 bogus errors across auth, nav and platform
    pages — all of them in files nobody had touched. Regenerate with `npm run build`
    or `npm run dev`, which run the plugin with the right options.
25. **A sort direction default must not leak into the unsorted branch.** `direction`
    defaults to `desc` because that is right for "newest first" lists, but plans are
    ordered by `sort_order` ascending. Applying `desc` there silently reversed the
    merchandising order. The unsorted branch orders on its own terms.
26. **Assert on `where('plans.data.0.name', …)`, not on `->json('plans.data')`.** An
    Inertia page is only JSON when the request carries Inertia's headers, so
    `->json()` on a plain `get()` throws "Invalid JSON was returned from the route".
    Use `assertInertia` with dotted paths, and pin `sort_order` in factories so the
    expected row order does not depend on what the factory filled in.
27. **A radio group needs a closed, small option set — do not force one.** The users
    and plans filters use `RadioGroup` because status and role are fixed vocabularies
    of three to five values. `subscription_payments.method` is free text typed by
    whoever logged the payment, so its option set is open and a closed list of
    choices would be a lie. That table filters on a received-on **date range** instead,
    which is the filter someone reconciling income actually wants.
28. **`decimal:2` casts serialise as a string, which silently breaks sorting.** The
    `amount` column was cast to `decimal:2`, so `$payment->amount` is `"1000.00"`.
    Left alone it sorts lexicographically — `"1000"` after `"900"` — and every
    consumer needs its own `Number()`. The controller casts once in `serialize()`
    instead. Money is whole-currency in this app, so a float is safe; a table that
    ever holds minor units must keep the string and cast at the edges.
29. **A Radix `Select` cannot submit "nothing selected".** Both the organization and
    plan pickers are optional, but `SelectItem value=""` is reserved for the
    placeholder. Each picker therefore renders a hidden `""` input alongside it —
    always present, so it submits `null` rather than omitting the key — which is
    what makes "no organization" a real choice rather than a missing one.
30. **`only` on a partial reload silently drops the props the sheet needs.** The
    organization and plan lists are `Inertia::optional()`, so they arrive only when
    the form asks. Every helper here therefore passes `only: ['payments', 'filters',
    'sort']` — without it, the debounced search would refetch the whole organization
    list on each keystroke. The catch is that a `->missing('organizations')`
    assertion on a plain request proves nothing about partial reloads; testing the
    optional prop needs `X-Inertia-Partial-Data`, or the test passes while the
    feature is broken.

## 11. Read-only tables

`platform/audit/index.tsx` is the one table here with no create, no edit, no
delete and **no Actions column at all**. The pattern still applies:

- Rows open a detail sheet on `onRowClick` — an audit entry's description is
  truncated in the row and belongs in full somewhere.
- The header band holds search, Filters and Export, all teal. There is no
  page-level CTA because there is nothing to create.
- The filter vocabulary splits by whether the option set is *closed*:
  `event` and `subject_type` grow with whatever code writes to the log, so they
  stay `Select`s; `organization` is one row per org, so it is `optional()` and
  loaded when the overlay opens.

`AuditService` is shared with the tenant audit view, so the sort allowlist lives
in the service and `query()` takes an optional sort argument that defaults to
the `latest('created_at')` every existing caller relied on. A shared service is
the usual reason a parameter has to be additive rather than required.

---

## 12. Traps from the last two migrations

31. **`Request::string()` returns a Stringable, and an object is always truthy.**
    `->when($request->string('search')->trim(), fn () => ...)` therefore ran the
    filter *even with no search term*. On most tables the damage was invisible —
    the clause it added was `like '%%'`, which matches everything — so the tests
    passed and the query merely carried a useless condition. On expenses it also
    bound `category = ''`, which matches nothing: the index returned **zero rows
    for every unfiltered request**. `->trim()->toString()` gives a real string.
    Fixing it touched five controllers at once, because the same line had been
    copied between them.
32. **A guarded `created_at` silently makes ordering assertions meaningless.**
    `Audit::create(['created_at' => ...])` drops the timestamp — it is not
    fillable — so the row lands at `now()` and every entry in the test appears
    to have the same time. The default-order test then asserted "newest first"
    against rows that were all identical, and passed or failed depending on
    insertion order. Backdated rows need `forceFill(['created_at' => ...])`
    after the insert.
33. **A radio group in a dense row is worse than a Select, even for a closed
    set.** Expenses has six fixed categories, and a radio group is right in the
    filters overlay where all six are visible at once. In the two-column form a
    Select reads better, so the same closed set is a dropdown there. The rule is
    about room to show the options, not about the size of the set alone.
