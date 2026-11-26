# 07 — Leases & Agreements (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §A3, §B6.

## Current state — files and how they link
- Backend: `app/Http/Controllers/Tenant/LeaseController.php` (index/export/remind/amend-rent/renewal-suggestion/end), `LeaseAdditionalChargeController.php`, `app/Actions/Leases/*` (amend, extras, bulk extras, reminders, export), `OccupantService::ensureLease` (auto-create per unit assignment), `LeaseAgreementTemplate` (placeholder merge + sanitize).
- Templates: `AgreementTemplateController.php` (org-wide + property-scoped CRUD, picker endpoint), `Create/Update/DeleteAgreementTemplate.php`.
- Frontend: `organisation/leases/index.tsx`, `occupants/agreement.tsx`, `agreement-templates/index.tsx` + `form.tsx`, TipTap `RichTextEditor`.
- Move-out: `MoveOutNoticeController` (tenant + searcher sides), `Acknowledge/Submit/DecideMoveOutNotice` actions.

## Landlord pain
- Agreement authoring (TipTap + `{{placeholders}}` + template library with org vs property scope) is operator-grade tooling shown to everyone. A landlord needs "standard agreement, fill names, print".

## Enterprise gap
- No rent-increment schedules, no multi-currency lease handling, no e-signature, leases excluded from trash by design (ended = archive) which is right but needs an explicit archive view.

## Revamp proposal
1. Ship one default agreement template per org at creation; quick-add path (doc 06) auto-attaches it — landlord prints without ever opening the template library.
2. Keep the full template studio (org + property scope, picker, TipTap) in the Pro lens (already out of the simple sidebar); no functional change.
3. Add to the lease model (non-breaking): `rent_increment_schedule` (annual %, effective date), `currency` enforcement per lease, `archived` scope for ended leases.
4. Renewal suggestions stay; add increment preview ("rent becomes X from date Y").

## Acceptance
- Default template exists for every org (backfilled for existing orgs).
- Quick-add tenant → printable agreement with zero template decisions.
- Ended leases leave the active list but remain queryable (archive filter).

## Next
Work on `08-payments-reconciliation.md` — the money pipeline leases feed.
