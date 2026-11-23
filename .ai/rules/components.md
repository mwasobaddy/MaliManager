---
paths:
  - resources/js/components/rich-text-editor.tsx
  - resources/js/components/property-picker-dialog.tsx
  - 'resources/js/components/**'
---

# Components

## Disable link/underline in StarterKit to avoid duplicate extensions

@tiptap/starter-kit v3 includes Link and Underline by default. This editor registers them explicitly too, which produced 'Duplicate extension names: [link, underline]' warnings and broke command registration -- e.g. editor.chain().setImage() threw 'setImage is not a function' so uploaded images never inserted. StarterKit.configure({ link: false, underline: false }) resolves it. Keep explicit Link/Underline extensions.

## Picker continuation cards and gating
The picker's bottom "Continue as admin" button is now a stacked set of teal (bg-brand-accent) cards: one "Continue to overall dashboard" card per organization the user can access (owner with >=1 property, or staff managing >1 property), plus the "Continue to admin dashboard" card gated by the platform 'access admin dashboard' permission. mustChoose = hasAssets && !canContinueAsAdmin && !canContinueToOrgDashboard. Owners are no longer forced to pick an asset.

## Bulk selection: bar appears, select-all is page-scoped, rows highlight

BulkDeleteBar (resources/js/components/bulk-delete-bar.tsx) renders only while a selection exists — never a permanent hint row with a disabled Delete — as a full-bleed teal strip flush between the table toolbar and the header, with Clear matching the sheet Cancel chrome. Tri-state select-all is page-scoped everywhere and lives in exactly one place per view: the DataTable/hand-table header checkbox for tables, the bar's own toggle (showSelectAll) for card lists that have no header. Selected rows and cards take `bg-muted/60` — strong enough to read on dark canvases — so the scope reads in the list as well as the count. Selection prunes on every data change, so a bulk payload can only name visible rows; a single click never reaches rows behind pagination or filters.

## Teal marks selection surfaces; always re-lock hover on Badge overrides

`bg-brand-accent text-on-accent` (no border) marks selection and assignment surfaces: the bulk bar, selected unit chips, table unit badges, and the view-sheet units panel (there with white text and an inverted-white occupied badge). The `outline` Badge variant carries a built-in `hover:bg-muted` that survives class overrides and flips the badge gray on hover — every teal/white badge override must add its own hover lock (`hover:bg-brand-accent`, `hover:bg-white`, `hover:bg-transparent`) or the hover state contradicts the resting state. Text on teal uses `text-on-accent` (and `/70` for secondary lines), never muted tokens.

## Toolbars stay one line; page CTAs collapse to icons on phones

Card toolbars (search + Filters/Import/Export icon buttons) keep one shared row at every width: the search is `flex-1 min-w-56`, the Filters button carries `ml-auto` so the row pins right, and anything beyond filters lives behind the `TableOverflowMenu` More button below `sm` (icon row `hidden sm:flex`, trigger `sm:hidden`). Toolbar icon buttons match the 36px search input on touch screens via `coarse:min-h-9 coarse:min-w-9` — the shared row reads as one 36px band instead of a tall button beside a short field. (Deliberate exception to the 44px coarse target, which row actions and the search keep: toolbar buttons are secondary, tightly grouped controls.) Page-header Add buttons (`LabelledIconButton`) collapse to their icon chip below `sm` — label hidden, `aria-label` carrying the name — sharing the H1 row instead of wrapping under it. Never stack search on its own line while buttons rag-doll below, and never put the action above the title: title first, actions right, at every width.

## Use ActionIconButton for table row actions
Table row actions use the shared ActionIconButton (resources/js/components/action-icon-button.tsx), not hand-rolled `<Button variant="ghost" title="...">`. It takes `icon` + `label` (label doubles as the tooltip text AND the aria-label, so include the row's name, e.g. label="Delete Acme"), plus `color`: view/edit/danger/brand/accent/teal. Colors come from design tokens (--action-view/--action-edit/--action-danger/--brand-primary/--brand-accent in resources/css/app.css) that already carry separate :root and .dark values, so NEVER add `dark:` variants to the color classes — the token swaps itself. Row actions are solid fills at rest with brightness-shift hover. Tooltip is the Radix shadcn one (global TooltipProvider, delayDuration 0), not the native `title` attribute. For submits, wrap in Inertia `<Form className="inline">` and pass `type="submit"`; do NOT use Form `as="button"` (that nests a button inside a button). With `asChild`, pass the child element and OMIT `icon`. The inner `variant="ghost"` leaks `hover:bg-accent hover:text-accent-foreground` (terracotta flash) onto every colored fill — `ghostHoverLocks` in the component re-locks each color's own hover (view/edit/danger re-state their fill so only the brightness filter shifts; the rest re-state their hover text). Never add another ActionIconButton color without a lock entry.

## ConfirmDeleteOverlay covers single, bulk and restore

One password modal for every destroy (resources/js/components/confirm-delete-overlay.tsx): `action` + optional `payload` (`{module, ids}` for bulk/trash), `method` (`delete`, or `post` for restores), `submitLabel`/`processingLabel` naming the count (`Delete 3 units`), `danger={false}` for the terracotta restore primary, `closeOnSuccess` for local-state callers (URL-driven callers close via the redirect), `onSuccess` to clear selection. Never build a second confirm dialog — extend this one's props instead (see the deleted `trash-password-dialog` precedent: duplication was removed, not kept).

## TableOverflowMenu collapses toolbars below sm

More button (`TableOverflowMenu`, resources/js/components/table-overflow-menu.tsx) replaces the Filters/Import/Export icon row below `sm` (`hidden sm:flex` row, `sm:hidden` trigger). Choosing an item closes the menu first, then opens that sheet — overlays never stack (Radix fight). The primary Add button stays out of the menu on its own row. Desktop is untouched: full icon row, no trigger. Exception: a lone utility button (one Filters, no import/export) stays visible at every width — the drawer only earns its place once a row of icons would wrap.

## Row actions read left to right in escalating consequence

Row actions read left to right in escalating consequence: status-changing actions first, then `edit` green, destructive red last — all as solid fills. Move out and Remind use solid `teal`: the `accent` tint reads washed-out and partial next to solid siblings. Never field two reds in one row: a status change is not a delete even when it ends something (a moved-out occupant keeps their history; only trash permanent-delete is destructive).

## Read-only sheets showcase actions in the header, not the footer

`ResponsiveOverlay` accepts an `actions` slot rendered in the sticky header next to the title (both Sheet and Drawer branches). A read-only view sheet keeps a Cancel-only footer and puts its top-level actions there — e.g. Agreement (primary) + Edit (green `bg-action-edit-fill`, matching its row icon) — following GitLab Pajamas, Frontile and Planview, which all put header actions beside close. Footers stay for the sheet's own submit + Cancel.

## Forms must cover every writable field, with live validation

Audit new inputs against the model `$fillable` plus the FormRequest rules before calling a form done — the missing gender input survived because the view showed the field but no sheet wrote it. Mirror server rules on blur for required and format fields (`required` text, `email` shape), show server errors first and local errors second through the same `InputError`, and disable the sheet's submit via `onValidityChange` while anything local fails. The server remains the authority; live validation only removes the round trip.

## Multi-select needs a searchable chip picker, not SearchableSelect

`SearchableSelect` is single-value only. For `unit_ids`-style multi-selects, build the searchable chip dropdown instead: a search input filtering the option list, removable chips carrying hidden `name[]` inputs, full keyboard flow (Escape closes, arrows optional). Prune to selected ids on every data change like the bulk bar does, and prefill dependent fields (rent from the single selected unit) off the same selection state.
