# 10 — Expenses (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §B3.

## Current state — files and how they link
- Backend: `app/Http/Controllers/Tenant/ExpenseController.php` (full CRUD under single `expense.manage` gate), expense model (Property/LandParcel target, optional Unit, categories, medialibrary receipts under `{org}/{asset}/expenses/`).
- Frontend: `organisation/expenses/index.tsx`, `form.tsx`.
- Platform mirror: `Platform/PlatformExpenseController.php` (same shape, `platform-expense.*` gates).

## Landlord pain
- Low: expense CRUD is simple enough. Residual friction: single `expense.manage` gate means anyone who can log a receipt can also delete history; no approval for big spends.

## Enterprise gap
- No approval workflow (caretaker logs → manager approves → finance pays), no budgets per property/branch, no recurring expenses, no petty-cash float handling.

## Revamp proposal
1. Keep current CRUD as "log expense"; add `status` (`pending → approved → paid`) with approval by `expense.approve` holders above a per-org threshold (small amounts auto-approve).
2. Add recurring-expense templates (monthly: caretaker salary, garbage, water) that generate drafts.
3. Add per-property monthly budget with over-budget flag on the dashboard (feeds 11).

## Acceptance
- Caretaker logs a receipt in the field; it routes for approval only when above threshold.
- Recurring costs require zero monthly re-entry.
- Budget breach is visible before month-end, not after.

## Next
Work on `11-maintenance.md` — the operational workflow that needs SLA thinking.
