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
1. Unify the asset model in UX: one "Assets" home with Building / Land tabs instead of two top-level modules; keep routes unchanged.
2. Bring section leases to parity with unit leases (agreement merge, renewal suggestion, arrears) rather than a parallel minimal path.
3. Add parcel status pipeline (available → reserved → leased/sold) feeding the listing surface (see 16).

## Acceptance
- Setup no longer asks Building-vs-Land; user reaches parcels naturally from Assets.
- A section lease produces the same agreement/arrears/reminder behaviour as a unit lease.

## Next
Work on `06-occupants.md` — people are where landlord drop-off is highest.
