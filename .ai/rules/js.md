---
paths:
    - 'resources/js/**'
---

# Js

## useHttp is for Inertia pages, not plain JSON APIs

Inertia v3 useHttp().get() sends X-Inertia and routes the response through the Inertia page machinery (isInertiaResponse requires an X-Inertia response header). It NEVER calls onSuccess for a plain JSON endpoint — results stay empty and onHttpException fires instead. For JSON APIs, use fetch() with X-Requested-With + credentials, as in global-search.tsx / property-picker-dialog.tsx.
