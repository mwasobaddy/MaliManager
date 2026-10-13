---
paths:
    - 'resources/js/**'
---

# Js

## useHttp is for Inertia pages, not plain JSON APIs

Inertia v3 useHttp().get() sends X-Inertia and routes the response through the Inertia page machinery (isInertiaResponse requires an X-Inertia response header). It NEVER calls onSuccess for a plain JSON endpoint — results stay empty and onHttpException fires instead. For JSON APIs, use fetch() with X-Requested-With + credentials, as in global-search.tsx / property-picker-dialog.tsx.

## Regenerate Wayfinder only via the Vite plugin
Never regenerate Wayfinder with `php artisan wayfinder:generate` on its own — it does not read vite.config.ts, where `wayfinder({ formVariants: true })` is set, so it rewrites every generated route without the `.form`/`.get`/`.post` helpers and `tsc` reports ~34 bogus "Property 'form' does not exist" errors across auth, nav and platform pages. Regenerate through `npm run build` (or `npm run dev`), which runs the Vite plugin. If tsc error count jumps after touching routes, suspect this before the code you just wrote.
