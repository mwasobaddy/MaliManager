---
paths:
  - resources/js/pages/welcome.tsx
  - 'resources/js/pages/**/*.tsx'
---

# Pages

## Welcome page = branded marketing landing
The home page (welcome.tsx) is the marketing landing converted from the root index.html mockup. Use Warm Earth tokens only (primary terracotta CTAs, border-border cards, status-success for positive data, brand-accent for "Live"/in-progress glow). Utilities live in resources/css/app.css: .grid-texture and the --animate-fade-in-up keyframe (hero entrance); prefer these over arbitrary inline values. Marketing CTA links point to login() since there is no register route; when auth.user is set, show Dashboard instead.

## Shared table components and URL-state rules for list pages
Tables use the shared primitives in resources/js/components/: DataTable (TanStack Table v8, manualSorting — pass ONLY the current page, sort via ?sort=&direction= with a server-side allowlist), ResponsiveOverlay (right-hand Sheet on desktop, centred Dialog under 768px via useIsMobile), StatusIcon (icon + tone, never colour alone per ui-design-system.md §1), ActionIconButton (icon-only row actions). Toolbar is merged: debounced live search (~350ms, never fires on mount, resets to page 1) plus an icon-only Filters button; filters live in the overlay. URL-driven overlays must read usePage().url, NEVER window.location — Inertia pushes history after render so window.location is stale and nothing re-renders. For argument-only POST/PATCH actions with no bound input, use router.patch(url, {data}) — an Inertia <Form> with no bound input submits an empty body. Full reference + traps: table-ui.md.
