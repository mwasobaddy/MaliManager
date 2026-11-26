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
1. Two-tier form: **built** — a "Quick add" sheet (name + phone + unit + rent, monthly/KES/moved-in now by default) posts to the same store endpoint; the full form is untouched. Verified in under a minute in the browser.
2. Make `email` nullable for quick add. **Built** — `StoreOccupantRequest` requires email *or* phone; `UpdateOccupantRequest` leaves email optional. A phone-only occupant gets a `User` account at creation (phone, `sms` channel, non-routable placeholder address) so they can sign in with their number immediately; `Person` matches by phone, and adding a real email later replaces the placeholder and links everything up (with a "complete profile" nudge on the view sheet).
3. Add occupants CSV import with the same per-row skip-and-report pattern as organization import. **Built** — org-scoped `POST /{property}/occupants/import` reusing `CreateOccupant` per row (active, first rent recorded), per-row errors surfaced in the sheet like units import. Verified in the browser including the failure report.
4. Phone login for phone-only tenants. **Built** — the login page accepts an email or phone number (`PhoneNumber` canonicalization, exact + legacy-format matching), delivers the OTP over email or SMS accordingly, and the OTP page adapts ("Check your texts", masked number). Verified end to end in the browser.
5. Keep `move-out`/`move-in`/`agreement` flows unchanged. **Unchanged.**

## Acceptance
- Caretaker adds a tenant in < 60 seconds with name + phone + house + rent. **Done (browser-verified).**
- Full-profile path (agreements, extras) untouched for operators who need it. **Done.**
- Import of 100 tenants reports per-row errors without aborting the file. **Done (pattern-tested; browser-verified with mixed file).**

## Next
Work on `07-leases-agreements.md` — the lease engine occupants feed into.
