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

## Use ActionIconButton for table row actions
Table row actions use the shared ActionIconButton (resources/js/components/action-icon-button.tsx), not hand-rolled `<Button variant="ghost" title="...">`. It takes `icon` + `label` (label doubles as the tooltip text AND the aria-label, so include the row's name, e.g. label="Delete Acme"), plus `color`: view/edit/danger/brand/accent. Colors come from design tokens (--action-view/--action-edit/--action-danger/--brand-primary/--brand-accent in resources/css/app.css) that already carry separate :root and .dark values, so NEVER add `dark:` variants to the color classes — the token swaps itself. Backgrounds are /12 tint, /20 on hover. Tooltip is the Radix shadcn one (global TooltipProvider, delayDuration 0), not the native `title` attribute. For submits, wrap in Inertia `<Form className="inline">` and pass `type="submit"`; do NOT use Form `as="button"` (that nests a button inside a button). With `asChild`, pass the child element and OMIT `icon`.
