# 03 — Navigation Shell (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §A6, §A10.

## Current state — files and how they link
- Frontend: `resources/js/components/app-header.tsx`, `app-sidebar-header.tsx`, layouts (`app-sidebar-layout`, `app-header-layout`), `global-search.tsx`, `notifications` (stub), `topbar-user-menu.tsx`, `property-picker-dialog.tsx`.
- Backend: `app/Http/Controllers/SearchController.php` (`GET /search`, tenant-aware via hostname), `App\Services\SearchService.php`, `AuthLanding` (staff landing, picker vs direct), `DashboardController` (central `dashboard` with property picker + acknowledge).
- Routes: central `dashboard` (picker) vs tenant `/{slug}/dashboard` and `/overview` (`tenant.overview`).

## Landlord pain
- Single-property orgs pay multi-property tax: slugs in every URL, picker modal, cross-subdomain hops that break back-button and mental model on phones. Top bar exposes search/notifications/avatar/AI from day one; sidebar exposes modules the org will never use.

## Enterprise gap
- No branch switcher (flat org picker breaks at 20+ properties), no favourites/recents at scale, no global command-palette scoping by branch, notification stub means no operational triage surface.

## Revamp proposal
1. Single-property mode: **built for the chooser, not the URL.** Landing already went direct (`AuthLanding`); now the picker stops force-opening on the central dashboard below two accessible assets (`DashboardController`, count check) and the sidebar "Switch property or land" hides itself below two destinations (`hasMultipleAssets` in the picker context). Slugs stay — they are the routing. `/properties` stays reachable.
2. Progressive shell: **built** as the Simple/Pro lens. `organizations.settings['mode']` (asked in `become-landlord`, switchable by owners in Staff → Roles → Security policy) is shared in `HandleInertiaRequests` context; `app-sidebar.tsx` trims to daily jobs + money + team in simple mode, with an owner-only "Show all features" switch and a dismissible Pro suggestion when the org outgrows simple (2nd asset, staff, >10 units). Routes are never gated — the lens only trims the sidebar.
3. Search stays but scopes to branch when branches land (see 15); keep PII gating (`manageUsers`). **Still open.**
4. Replace the notification stub with a real centre (feeds 14): rent-due, arrears, SLA breach, approval tasks. **Still open.**

## Acceptance
- 1-property user never sees a forced picker or a one-choice switcher; 20-property user gets branch-grouped switching (branches still open).
- New org nav fits on a phone screen without scrolling; switching to the full view is one tap away, never a dead end. **Done via mode switch.**

## Next
Work on `04-properties-units.md` — the asset foundation everything else hangs off.
