---
paths:
  - 'app/**'
  - 'resources/js/**'
  - 'database/**'
  - 'routes/**'
---

# Scalability

Write every query, endpoint and list for 100x today's volume. Scale is a
design input, not a rewrite trigger: code that only works small is
unfinished, and "we will paginate later" is a promise to rewrite.

## Backend

- Every unbounded user-facing list paginates — 15 per page is the house
  standard, matching the platform controllers.
- Never `::all()` or an unbounded `->get()` on a read path. Counts and
  sums happen in SQL, not on hydrated collections.
- No N+1: eager-load what the response touches. Jobs and commands that
  walk many rows use `cursor()`/`chunk()` and keep going past single-row
  failures (log and skip, like the snapshot capture).
- History comes from snapshot tables, never reconstructed live and never
  fanned out across tenant databases per request.
- Writes stay idempotent where reruns are possible (unique date/org keys,
  update-in-place), so a retried job or double-click cannot double-count.

## Frontend

- Never render unbounded node lists and never fetch-all-then-filter.
  Page at 15; search, sort and filter run server-side with allowlists
  (see pages.md table rules).
- Charts plot pre-aggregated series capped by construction (trailing 30
  days, 12 months, six cohorts) — never raw row dumps.

## Schema

- Index every foreign key and every column a list endpoint filters or
  sorts on, in the same migration that introduces the query.
- Money stays `decimal(14,2)` (see money.md). A second representation is
  a conversion bug at every boundary, not an optimization.
