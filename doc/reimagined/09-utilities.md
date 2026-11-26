# 09 — Utilities (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §A9.

## Current state — files and how they link
- Backend: `app/Http/Controllers/Tenant/UtilityReadingController.php` (index/export/store/bulk), `UtilityChargeController.php` (charges CRUD), `app/Actions/Metering/*` (record, bulk record, create/update/delete charge, attach-to-unit, export).
- Validation: `StoreUtilityChargeRequest` requires `unit_ids`; readings are per-unit rows with one shared date for bulk.
- Frontend: `organisation/utilities/index.tsx` (readings + charges + setup + reading grid).
- Gates: readings under `unit.manage` (caretaker walks the route), charges under `utility.manage` (landlord-set rates).

## Landlord pain
- Dual model (readings vs charges), three kinds (metered/fixed/shared), rate setup, bulk grid, CSV export — for a reality that is usually "split water/token into rent".

## Enterprise gap
- Small: the engine is actually adequate. Missing: prepaid-token import, per-branch utility tariffs, vacancy handling (who pays when a unit is empty), utility arrears separated from rent arrears.

## Revamp proposal
1. Default path: "Add bill to rent (fixed amount / split equally across occupied units)" — one screen, no rates.
2. Gate the full engine (metered rates, bulk reading grid, per-unit assignment, export) behind "I have meters".
3. Add vacancy rule per charge (landlord absorbs / split among occupied) and show utility vs rent split in arrears (feeds 08/11).

## Acceptance
- Landlord adds a water bill to rent without learning what a charge kind is.
- Metered operators keep today's full flow unchanged.
- Vacant units never silently inflate other tenants' bills.

## Next
Work on `10-expenses.md` — the other side of the property P&L.
