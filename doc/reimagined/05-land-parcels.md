# 05 — Land Parcels (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §A5.

## Current state — files and how they link
- Backend: `app/Http/Controllers/Tenant/LandParcelController.php`, `LandParcelSectionController.php` (sub-plots + section leases), `app/Services/LandParcelService.php`, `LandParcel` model (slug, org-scoped binding, `photos` media, SoftDeletes).
- Routes: org-level `/land-parcels*` (`routes/tenant.php:447-504`) — deliberately outside the `has-property` group; section leases under `lease.manage`.
- Frontend: `resources/js/pages/organisation/land-parcels/index.tsx`, `create.tsx`, `show.tsx`, `edit.tsx`.
- Pickers: `PropertyController::index` lists parcels alongside properties.

## Landlord pain
- Parcel flow mirrors buildings well enough; the damage is upstream (forced Building-vs-Land choice in setup, doc 02) and in lease coverage (parcel leases less mature than unit leases).

## Enterprise gap
- Sections are a good start but there is no plot-lifecycle (survey → subdivision → titling → sale/lease), no per-section P&L, no branch grouping for land banks.

## Revamp proposal
1. Unify the asset model in UX: one "Assets" home with Building / Land tabs instead of two top-level modules; keep routes unchanged. **Setup fork removed in doc 02 (Building is the default path); shared Assets home still open.**
2. Bring section leases to parity with unit leases (agreement merge, renewal suggestion, arrears) rather than a parallel minimal path. **Partially built: agreements were already merged at creation; lease visibility, renewal suggestions and arrears-ageing for sections stay open (see 07/13). What landed here: the section lifecycle is complete — reserve/release transitions with guards, duplicate-lease refusal, and password-confirmed early termination that frees the section and starts the deposit-refund review, mirroring unit leases.**
3. Add parcel status pipeline (vacant → reserved → leased, plus maintenance) feeding the listing surface (see 16). **Section-level pipeline built (`vacant ↔ reserved`, `leased` set/cleared by the lease lifecycle). Whole-parcel sale/lease pipeline still open.**

## Acceptance
- Setup no longer asks Building-vs-Land; user reaches parcels naturally from Assets. **Done in doc 02.**
- A section lease produces the same agreement/arrears/reminder behaviour as a unit lease. **In progress: agreements merge; arrears and reminders ride the shared Lease engine; per-section visibility and termination now exist. Renewal suggestions for sections still open.**

## Next
Work on `06-occupants.md` — people are where landlord drop-off is highest.
