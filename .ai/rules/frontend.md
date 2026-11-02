---
paths:
  - resources/js/**
  - resources/css/**
---

# Frontend Rules

## Wayfinder

- Always regenerate with `php artisan wayfinder:generate --with-form`. The CLI defaults to omitting `.form` variants, which breaks `useForm`/`<Form>` pages with `TS2339: Property 'form' does not exist`. The vite config's `formVariants: true` only applies to dev-time generation, not the CLI.
- Route functions with URL params expose `.form` on the function object, not the call result: use `update.form(staff.id)` / `destroy.form(staff.id)`, never `update(staff.id).form()`.

## Create, edit and view are sheets; delete is a modal

| Job | Surface |
| --- | --- |
| Create | `ResponsiveOverlay` — sheet from the right, drawer from the bottom |
| Edit | `ResponsiveOverlay` — the same sheet |
| View | `ResponsiveOverlay` — the same sheet, read-only |
| Delete | `ConfirmDeleteOverlay` — centred modal, password verified server-side |

- Never a full page for a record, and never a centred modal for a form. The one modal is delete.
- Open state comes from the URL through `useOverlayParam()`, never `useState`. Close returns to the
  list URL; a successful save redirects to the index so the sheet closes itself.
- A record URL (`/occupants/5`) redirects to the query form the page reads; constrain it with
  `whereNumber` or it swallows `/occupants/create`.
- Never stack two overlays — Radix fights over the focus trap.
- A delete modal is not the gate: the route permission authorizes it and a `Destroy*Request` with
  `current_password` verifies the actor. Add both, or the password field is theatre.

Components: `components/responsive-overlay.tsx`, `components/confirm-delete-overlay.tsx`. Full policy,
URL mechanics and reasoning: the `overlays.md` reference in the `laravel-feature-slice` skill.
