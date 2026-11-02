---
paths:
  - resources/js/**
  - resources/css/**
---

# Frontend Rules

## Wayfinder

- Always regenerate with `php artisan wayfinder:generate --with-form`. The CLI defaults to omitting `.form` variants, which breaks `useForm`/`<Form>` pages with `TS2339: Property 'form' does not exist`. The vite config's `formVariants: true` only applies to dev-time generation, not the CLI.
- Route functions with URL params expose `.form` on the function object, not the call result: use `update.form(staff.id)` / `destroy.form(staff.id)`, never `update(staff.id).form()`.

## Overlays are chosen by the job, and always URL-based

| Job | Surface |
| --- | --- |
| Create, 3–5 fields | `FormModal` — centred dialog |
| Create with repeated groups, steps, or heavy validation | full page |
| Edit | `ResponsiveOverlay` — sheet from the right, drawer from the bottom |
| View | full page; a sheet only as a Quick View peek from a table |
| Delete | `ConfirmDeleteOverlay` — always a modal, password verified server-side |

- Open state comes from the URL through `useOverlayParam()`, never `useState`. Close returns to the
  list URL; a successful save redirects to the index so the overlay closes itself.
- Never stack two overlays — Radix fights over the focus trap.
- A delete modal is not the gate: the route permission authorizes it and a `Destroy*Request` with
  `current_password` verifies the actor. Add both, or the password field is theatre.

Components: `components/form-modal.tsx`, `components/responsive-overlay.tsx`,
`components/confirm-delete-overlay.tsx`. Full matrix, URL mechanics and reasoning: the `overlays.md`
reference in the `laravel-feature-slice` skill.
