# 13 — Reports & Analytics (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §B6.

## Current state — files and how they link
- Backend: `app/Http/Controllers/Tenant/ReportController.php` (index/data/csv/print), `OrganizationDashboardController.php` (`/overview`), `DashboardService` / `OrganizationDashboardService`, `ReportService` (`TYPES = occupancy, leases, expenses, maintenance, market`), `metrics:snapshot` cron, `PlatformOverviewService` (admin tabs, real subscription income vs platform expenses).
- Frontend: `organisation/reports/index.tsx` + `print.tsx`, `organisation/dashboard.tsx`, `platform/dashboard.tsx`, permission-gated tabs (`view advanced/organization/searcher/occupant metrics`), year+month filters with daily granularity.
- Constraints: `ReportService::expenses()` casts sums to float and hardcodes `' KES'`; `PlatformOverviewService` notes multi-currency needs per-currency buckets; aggregates must scope to `PropertyAccessService::organizationsWithProperties` (staff leak guard, `.ai/rules/services.md`).

## Landlord pain
- Reports page is operator-shaped (five datasets, preset+custom ranges, CSV + print). A landlord needs three answers: who hasn't paid, what's empty, what broke.

## Enterprise gap
- No `rent_collection`, `arrears_ageing`, or `property_pl` datasets; single-currency sums are meaningless across USD/KES; no portfolio roll-up, no scheduled board pack.

## Revamp proposal
1. Default dashboard answers the three landlord questions first (arrears list, vacancy list, open issues) — the full tab set lives behind the Pro lens (built, see 03), so simple-mode landlords never meet it by accident.
2. New datasets: `rent_collection` (collected vs expected, match rate), `arrears_ageing` (current/30/60/90+), `property_pl` (rent + utilities − expenses − fees). Per-currency buckets + FX table; never sum across currencies.
3. Scheduled board pack (PDF, weekly/monthly) to owners/managers; branch filter on every dataset from branch day one.
4. Keep the `accessible-properties` scoping invariant; add a test per new dataset.

## Acceptance
- Landlord opens the app and sees who owes what without touching Reports.
- Finance exports arrears-ageing per currency per branch.
- No float comparisons in tests (cast both sides; `.ai/rules/testing.md` SQLite rule).

## Next
Work on `14-ai-notifications.md` — the assist and nudge layer on top of these numbers.
