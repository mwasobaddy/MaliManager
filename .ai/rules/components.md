---
paths:
  - resources/js/components/rich-text-editor.tsx
  - resources/js/components/property-picker-dialog.tsx
---

# Components

## Disable link/underline in StarterKit to avoid duplicate extensions

@tiptap/starter-kit v3 includes Link and Underline by default. This editor registers them explicitly too, which produced 'Duplicate extension names: [link, underline]' warnings and broke command registration -- e.g. editor.chain().setImage() threw 'setImage is not a function' so uploaded images never inserted. StarterKit.configure({ link: false, underline: false }) resolves it. Keep explicit Link/Underline extensions.

## Picker continuation cards and gating
The picker's bottom "Continue as admin" button is now a stacked set of teal (bg-brand-accent) cards: one "Continue to overall dashboard" card per organization the user can access (owner with >=1 property, or staff managing >1 property), plus the "Continue to admin dashboard" card gated by the platform 'access admin dashboard' permission. mustChoose = hasAssets && !canContinueAsAdmin && !canContinueToOrgDashboard. Owners are no longer forced to pick an asset.
