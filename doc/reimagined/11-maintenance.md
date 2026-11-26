# 11 — Maintenance (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §A10, §B8.

## Current state — files and how they link
- Staff side: `app/Http/Controllers/Tenant/MaintenanceController.php` (index/update/destroy), `MaintenanceNoteController.php`, `app/Actions/Maintenance/Create/Update/DeleteMaintenanceRequest.php`, `AddMaintenanceNote.php`, `SuggestMaintenanceTriage.php` (AI triage, incl. vision).
- Request shape: `StoreMaintenanceRequest` requires title+description+IDs+priority+photos; states `opened → assigned → in_progress → resolved → closed`; `allowed_transitions` in `maintenance/index.tsx`.
- Occupant side: `Searcher/MyMaintenanceController.php` (`searcher/maintenance` index/store, active-lease holders only, ≤3 photos under `{org}/{property}/maintenance/`).
- Scheduler: no maintenance-SLA job exists (`routes/console.php` has rent/lease/billing/digest only); `ReportService::maintenance()` reports counts + avg days.

## Landlord pain
- Forced title/description/priority + 5 states for "Hse 3 tap leaking + photo". AI triage and photo vision are invisible value until the basics take 10 seconds.

## Enterprise gap
- No `sla_due_at`/`escalated_at`, no response/resolve timers by priority, no auto-escalation, no breach dashboard. Averages after the fact are not SLA management.

## Revamp proposal
1. **Log issue** = photo + unit + voice/text; defaults (`priority=medium`, `status=opened`); title auto-derived. Full edit stays for managers.
2. SLA policy per priority/asset (`response_hours`, `resolve_hours`); columns `sla_due_at`, `escalation_level`; scheduled escalator with assignee/owner nudges (feeds 14).
3. Keep AI triage (`SuggestMaintenanceTriage`) but run it post-creation as an assist ("suggested priority: high — broken pipe"), never as a creation gate.
4. Branch-scoped maintenance views from day one of branches (doc 04).

## Acceptance
- Caretaker logs an issue in ≤ 20 seconds with one photo.
- Breach list (`sla_due_at < now`, unacknowledged escalations) exists per branch.
- Occupant flow unchanged (already the simplest path in the system).

## Next
Work on `12-inspections-deposits.md` — the two money-adjacent field workflows.
