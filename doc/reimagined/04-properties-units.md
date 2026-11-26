# 04 — Properties & Units (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §A6, §B2.

## Current state — files and how they link
- Backend: `app/Http/Controllers/Tenant/PropertyController.php` (index/create/store/dashboard), `UnitController.php` (CRUD + import/export), `app/Actions/Properties/CreateProperty.php`, `AddPropertyUnit.php`, `UpdatePropertyUnit.php`, `ImportUnits.php`, `ExportUnits.php`, `PropertyHelpers.php`.
- Frontend: `resources/js/pages/organisation/properties/index.tsx`, `create.tsx`, `dashboard.tsx`, `organisation/unit/index.tsx`.
- Platform: `Platform/PropertyTypeController.php` (central catalog tenant forms read).
- Storage: `App\Support\StorageLayout`, `TenantPathGenerator` (medialibrary paths keyed by slug at creation, never moved on rename); `has-property` gate blocks tenant routes until first property.

## Landlord pain
- Property creation + units is actually reasonable; the pain is surrounding: slug URLs, mandatory property context for everything, and import/export buried where a landlord pasting "Hse1..Hse8" by hand needs it most.

## Enterprise gap
- No `branches` layer: delegation is flat per-asset, reporting loops all properties org-wide, picker is org-flat. A 20-branch operator cannot do branch P&L or branch-scoped staff views.

## Revamp proposal
1. Introduce `branches` (org → branch → property/parcel). **Built**: `branches` table + `Branch` model, Main branch auto-created per org (backfilled), `branch_id` on properties and land parcels defaulting to Main, owner-only branch creation from the properties page. Delegation is branch-inheritable (one `Branch`-type row covers current and future assets); per-asset delegation stays as override. Verified end to end: a branch-delegated caretaker sees a property added *after* the delegation with no re-invite.
2. Keep per-asset CRUD identical; add `branch_id` filter to every dashboard/report/arrears query from day one. **Partially built**: property and land-parcel indexes filter by branch; aggregate filters (dashboard, reports, arrears) stay open for docs 11/13.
3. Unit creation: keep form, but promote "paste a list" (one name per line → bulk create) next to CSV import. **Built in doc 02.**
4. Property dashboard becomes the branch-aware operational home (occupancy, arrears, open issues) — detail in 11. **Still open.**

## Acceptance
- Existing single-branch orgs see zero change (one default branch, hidden branch UI until a second branch exists). **Done.**
- Branch manager sees only their branch's properties, figures, staff — verified by a Feature test with two branches. **Done** (plus browser verification of the inheritance case).
- Pasting 8 unit names creates 8 units in one action. **Done in doc 02.**

## Next
Work on `05-land-parcels.md` — the sibling asset type that should stop being a fork.
