---
paths:
  - 'resources/js/**'
---

# Skeletons

Async content renders a skeleton shell, never a blank gap and never a
bare spinner. A skeleton is a pulsing wireframe of the layout that is
coming (same cards, same rows, `bg-skeleton` blocks where the figures
will land), so the page never reflows when data arrives.

## When skeletons apply

- Inertia deferred props: every deferred section pairs with a skeleton
  branch on `== null` (the prop type carries `null | undefined`).
  `null` means "no access" (permission note) and `[]` means "empty"
  (empty state) — the three states never share a branch.
- Paginated refetches keep stale content via `preserveState` instead of
  skeletonizing; skeletonizing on every page turn is flicker, not
  feedback.
- Do not invent async gaps: if the payload already carries the data,
  render it. Reach for `Inertia::defer()` first for anything that costs
  a visibly slow query, and only then for its skeleton.

## Shape

- Pulse with `animate-pulse` on `bg-skeleton` blocks. No custom shimmer
  gradients, no spinners as the sole indicator.
- Skeletons are `aria-hidden`: the text that matters (deltas, counts,
  conversions) is already beside them, and live regions announce the
  loaded content, not the loading.
- Match the real layout's dimensions (card count, row count, chart
  height) so arrival shifts nothing. Line blocks stand in for rows and
  text; a chart gets a chart-sized block (`ChartSkeleton`), never
  paragraph lines — the shapes have nothing in common.
