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
1. Ship one default agreement template per org at creation; quick-add path (doc 06) auto-attaches it — landlord prints without ever opening the template library. **Built** — `CreateOrganization` ships a "Standard Residential Agreement" (org scope, tokens that always resolve) via `CreateDefaultAgreementTemplate::ensureForOrg`; a backfill migration covers existing orgs that never set up a library. `ensureLease` attaches the org default whenever no text was authored (quick add and untouched editors both count), and leases always carry a currency (form pick, else org default, else KES).
2. Keep the full template studio (org + property scope, picker, TipTap) in the Pro lens (already out of the simple sidebar); no functional change.
3. Add to the lease model (non-breaking): `rent_increment_schedule` (annual %, effective date), `currency` enforcement per lease, `archived` scope for ended leases. **Built** — nullable JSON schedule + `Lease::incrementPreview()` ("rent becomes X from date Y"), `scopeArchived()` for the explicit archive view. The leases index defaults to the active book; `status=ended` is the archive, `status=all` restores everything.
4. Renewal suggestions stay; add increment preview ("rent becomes X from date Y"). **Built** — the amend-rent sheet takes an optional yearly % + effective date (emptying the percent clears it), the lease view shows the scheduled increase, and the renewal dialog prints the preview next to the AI suggestion.

## Acceptance
- Default template exists for every org (backfilled for existing orgs).
- Quick-add tenant → printable agreement with zero template decisions.
- Ended leases leave the active list but remain queryable (archive filter).

## Next
Work on `08-payments-reconciliation.md` — the money pipeline leases feed.
