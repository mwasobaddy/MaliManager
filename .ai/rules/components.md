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

## Toolbars stay one line; page CTAs collapse to icons on phones

Card toolbars (search + Filters/Import/Export icon buttons) keep one shared row at every width: the search is `flex-1 min-w-56`, the Filters button carries `ml-auto` so the row pins right, and anything beyond filters lives behind the `TableOverflowMenu` More button below `sm` (icon row `hidden sm:flex`, trigger `sm:hidden`). Toolbar icon buttons match the 36px search input on touch screens via `coarse:min-h-9 coarse:min-w-9` — the shared row reads as one 36px band instead of a tall button beside a short field. (Deliberate exception to the 44px coarse target, which row actions and the search keep: toolbar buttons are secondary, tightly grouped controls.) Page-header Add buttons (`LabelledIconButton`) collapse to their icon chip below `sm` — label hidden, `aria-label` carrying the name — sharing the H1 row instead of wrapping under it. Never stack search on its own line while buttons rag-doll below, and never put the action above the title: title first, actions right, at every width.

## Use ActionIconButton for table row actions
Table row actions use the shared ActionIconButton (resources/js/components/action-icon-button.tsx), not hand-rolled `<Button variant="ghost" title="...">`. It takes `icon` + `label` (label doubles as the tooltip text AND the aria-label, so include the row's name, e.g. label="Delete Acme"), plus `color`: view/edit/danger/brand/accent. Colors come from design tokens (--action-view/--action-edit/--action-danger/--brand-primary/--brand-accent in resources/css/app.css) that already carry separate :root and .dark values, so NEVER add `dark:` variants to the color classes — the token swaps itself. Backgrounds are /12 tint, /20 on hover. Tooltip is the Radix shadcn one (global TooltipProvider, delayDuration 0), not the native `title` attribute. For submits, wrap in Inertia `<Form className="inline">` and pass `type="submit"`; do NOT use Form `as="button"` (that nests a button inside a button). With `asChild`, pass the child element and OMIT `icon`.

## ConfirmDeleteOverlay covers single, bulk and restore

One password modal for every destroy (resources/js/components/confirm-delete-overlay.tsx): `action` + optional `payload` (`{module, ids}` for bulk/trash), `method` (`delete`, or `post` for restores), `submitLabel`/`processingLabel` naming the count (`Delete 3 units`), `danger={false}` for the terracotta restore primary, `closeOnSuccess` for local-state callers (URL-driven callers close via the redirect), `onSuccess` to clear selection. Never build a second confirm dialog — extend this one's props instead (see the deleted `trash-password-dialog` precedent: duplication was removed, not kept).

## TableOverflowMenu collapses toolbars below sm

More button (`TableOverflowMenu`, resources/js/components/table-overflow-menu.tsx) replaces the Filters/Import/Export icon row below `sm` (`hidden sm:flex` row, `sm:hidden` trigger). Choosing an item closes the menu first, then opens that sheet — overlays never stack (Radix fight). The primary Add button stays out of the menu on its own row. Desktop is untouched: full icon row, no trigger.
