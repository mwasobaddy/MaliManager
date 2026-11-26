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
1. Single-property mode: when `properties + parcels == 1`, land directly on that dashboard, hide picker/slug, keep `/properties` under Advanced.
2. Progressive shell: default nav = Home, Tenants, Money, Issues, More. Everything else (Reports, AI, Templates, Roles, Audit, Trash) moves under More/Advanced until the org qualifies (property count, plan tier).
3. Search stays but scopes to branch when branches land (see 15); keep PII gating (`manageUsers`).
4. Replace the notification stub with a real centre (feeds 14): rent-due, arrears, SLA breach, approval tasks.

## Acceptance
- 1-property user never sees a picker or slug; 20-property user gets branch-grouped switching.
- New org nav fits on a phone screen without scrolling; Advanced is one tap away, never a dead end.

## Next
Work on `04-properties-units.md` — the asset foundation everything else hangs off.
