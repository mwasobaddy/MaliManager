# 06 — Occupants (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §A3.

## Current state — files and how they link
- Backend: `app/Http/Controllers/Tenant/OccupantController.php` (index/show/create/store/edit/update/move-in/move-out/destroy/agreement), `app/Actions/Occupants/CreateOccupant.php`, `UpdateOccupant.php`, `DeleteOccupant.php`, `MoveInOccupant.php`, `MoveOutOccupant.php`, `TerminateLeaseOccupant.php`, `OccupantHelpers.php`.
- Validation: `StoreOccupantRequest.php:23-31` — `email` required, `unit_ids` required; lease fields optional at validation but presented together in the form.
- Frontend: `organisation/occupants/index.tsx`, `agreement.tsx`, `components/occupant-form-fields.tsx` (template picker + TipTap + extras + rent-frequency logic).
- Identity: links global `Person` (find-or-create by email) + `User` (`PlatformRole::Occupant`, `onboarded_at` so they skip onboarding).

## Landlord pain
- Email-required + full lease + agreement in one transaction: a caretaker with "John, 0712…, Hse 3, 5k" cannot proceed. One validation failure loses the whole record.

## Enterprise gap
- No duplicate-person merging, no ID-verification hook, no bulk tenant import with per-row error report (org-import has this pattern; occupant import does not), no cross-org tenant history view for operators.

## Revamp proposal
1. Two-tier form: **Quick add** (`first_name + phone + unit + rent_amount`; defaults monthly/KES/move-in now) vs **Full profile** (email, national ID, agreement, extras). Quick add creates Person+Occupant+Lease; email/User-linking deferred with a "complete profile" nudge.
2. Make `email` nullable for quick add (backend: relax `StoreOccupantRequest`, generate placeholder identity keyed by phone + org).
3. Add occupants CSV import with the same per-row skip-and-report pattern as organization import.
4. Keep `move-out`/`move-in`/`agreement` flows unchanged; they already match reality.

## Acceptance
- Caretaker adds a tenant in < 60 seconds with name + phone + house + rent.
- Full-profile path (agreements, extras) untouched for operators who need it.
- Import of 100 tenants reports per-row errors without aborting the file.

## Next
Work on `07-leases-agreements.md` — the lease engine occupants feed into.
