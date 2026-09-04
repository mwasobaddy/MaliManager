---
paths:
    - 'app/Http/Controllers/Tenant/**'
---

# Tenant

## Org property flow is scoped to the current tenancy

Tenant controllers read the org from TenancyContext::organization() and never trust route params for scoping. Property route-model binding is org-scoped via Property::resolveRouteBindingQuery (slug binding). Plan limits (properties_limit / units_limit) are enforced in App\Services\PropertyService::create/addUnit, which throw DomainException caught by controllers into a 'plan' validation error.

## Property management URLs are top-level `/{property:slug}/...`

Property-scoped routes live at the top level of the tenant subdomain, without a /properties prefix: `/{property:slug}/dashboard` (tenant.properties.dashboard) and `/{property:slug}/units` (tenant.properties.units.store). The /properties* routes are org-level (picker: index, create, store) and are excluded from the property context. AuthLanding sends a single-property org straight to `/{slug}/dashboard`; multi-property orgs land on the /properties picker. The picker (index + create) renders with TenantPickerLayout (no app sidebar); property pages render with AppLayout so the navbar appears only when a property is selected. HandleInertiaRequests shares tenant.organization and tenant.property; AppSidebar reads tenant.property to build property-scoped nav (Dashboard + Switch property).

## Staff = non-owner members with a sub-role + property delegations

A staff member is an OrganizationUser pivot row (is_owner=false, sub_role_id set, status=active) plus Delegation rows (delegatable_type=Property) for the properties they manage. All writes go through App\Services\StaffService (atomic transaction): create/update sync property delegations by deleting then re-creating; softDelete removes membership + delegations and soft-deletes the user only when they belong to no other org. Users are found-or-created by email (no unique rule in StoreStaffRequest) and get onboarded_at set so they skip onboarding. Permission gating uses the `sub-permission:staff.X` middleware alias (EnsureSubPermission) with keys from SubPermissionKey (StaffManage/Create/Edit/Delete). Owners bypass checks; other members must hold the key via their sub-role. The default caretaker bundle ships StaffManage/Create/Edit (13 perms, NOT StaffDelete). Staff login lands on their org subdomain: 0 assigned props -> /properties picker with a warning toast, 1 -> /{slug}/dashboard, 2+ -> /properties picker. PropertyController::index scopes non-owners to delegated props; dashboard()/UnitController::store() authorizePropertyAccess (404 if not delegated); property create/store are owner-only via authorizePropertyMutation. HandleInertiaRequests shares tenant.permissions (string[] of sub-permission keys) so the sidebar/UI can gate on it.

## Occupant = global Person/User linked to units within one property

An occupant is an Occupant row (unique organization_id+person_id, status in active/inactive/moved_out, softDeletes) linking a global Person (identity, one per email) to units via the occupant_unit pivot (unique occupant_id+unit_id, created_by). All writes go through App\Services\OccupantService (atomic): create re-activates a trashed occupant for the same person; find-or-create User by email and assign PlatformRole::Occupant (role count in RbacTest is 4) with onboarded_at set; update syncs unit assignments by detaching only the current property's units then attaching validated unit_ids. unit_ids are validated with Rule::exists('units','id')->where(property_id) so cross-property units are rejected. softDelete detaches all units but keeps the Person/User (global identities). Routes are `/{property:slug}/occupants*` gated by `sub-permission:occupant.X`; the default caretaker bundle ships OccupantManage/Create/Edit (NOT Delete), agent ships only OccupantManage. OccupantController::index only lists occupants with a unit in the property (whereHas units scoped to property) and 404s via authorizePropertyAccess when the member is not delegated; an occupant from another property of the same org is simply absent from that property's index (no abort needed). Controllers present person fields (first_name/last_name/email/phone/national_id) plus property-scoped units and unit_ids.

## Land parcels are a separate org-level asset (not a Property type)

A land parcel is an App\Models\LandParcel row (separate land_parcels table, slug unique per org, SoftDeletes, implements HasMedia/InteractsWithMedia with the 'photos' collection). Route-model binding is org-scoped via LandParcel::resolveRouteBindingQuery (slug). Land parcel routes are ORG-LEVEL and live in the `['auth']` route group alongside staff — they must NOT sit inside the `['auth','has-property']` group, because has-property (EnsureOrganizationHasProperty) redirects any org without a Property to /properties/create, and land parcels have no property param. Routes: /land-parcels (index/show, sub-permission:land_parcel.manage), /land-parcels/create + POST /land-parcels (land_parcel.create), /land-parcels/{land_parcel}/edit + PUT (land_parcel.edit), DELETE /land-parcels/{land_parcel} (land_parcel.delete). The default caretaker bundle ships LandParcelManage/Create/Edit (NOT Delete); agent ships only LandParcelManage. index/create/store are gated by `sub-permission:land_parcel.*`; the controller's authorizeAccess lets owners through and 404s non-owners who are not delegated; DELETE is owner-only (caretaker hits 403 via the route gate).

## Land parcel manager delegation uses user ids, resolved to membership ids

The create/edit forms send `manager_ids` as USER ids (the managers() helper returns each member's `id => $user->id`). App\Services\LandParcelService::syncDelegations resolves those user ids to OrganizationUser membership ids, then stores Delegation rows (delegatable_type=LandParcel::class, organization_user_id=membership id). The `manager_ids.*` validation rule is `exists:users,id` (NOT organization_user,id). LandParcelService::managerUserIds returns user ids for pre-checking the edit form; delegatedLandParcelIds (StaffService) returns parcel ids for index scoping. NOTE: StaffService must import App\Models\LandParcel or `LandParcel::class` resolves to the wrong namespace and delegation scoping silently returns empty.

## Boolean form fields must use '1'/'0', never 'true'/'false'

Laravel's `boolean` validation rule uses strict comparison and rejects the strings 'true'/'false'; only true/false/1/0/'1'/'0' pass. The land_parcel `available_for_lease` hidden input and any boolean form field must submit '1'/'0'. Sending 'true'/'false' fails validation with "must be true or false".

## Property picker lists land parcels too

PropertyController::index (in the has-property group) now also passes `land_parcels` (org-scoped to delegated ids for non-owners), `canManageLandParcels` (land_parcel.manage) and `canCreateLandParcel` (land_parcel.create). The properties/index page renders a Land parcels section when canManageLandParcels is true. AuthLanding sends a fresh org (no properties AND no parcels) to /setup/first-asset (not /properties/create) so onboarding can offer both asset types; a single asset of either kind lands directly on it.

## Controllers catch Throwable and flash a toast

Tenant write actions wrap mutations in try/catch, Log::error the failure with ids, and return back()->withErrors() so nothing fails silently. Success paths use Inertia::flash('toast', ['type' => 'success', 'message' => ...]) consumed by the use-flash-toast hook; the frontend delete dialog confirms the actor's current password (currentPasswordRules) before POSTing the soft-delete.
