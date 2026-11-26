# 16 — Platform Admin, Billing & Listing API (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §B1, §B7.

## Current state — files and how they link
- Platform modules (`routes/platform.php`, all `platform.*`): `OrganizationController` (CRUD + CSV import/export with per-row skip-and-report, impersonate), `UserController`, `PlanController`, `SubscriptionPaymentController`, `PlatformExpenseController`, `PropertyTypeController`, `RolesController`, `AuditController`, `TrashController`, `PlatformController` (impersonate + `impersonated_by` session key), `AssistantController`.
- Tenant billing: `Tenant/BillingController.php` (`/billing`, M-Pesa STK + card verify, callbacks → `SubscriptionPayment`), `ChoosePlan/InitiateMpesa/HandleMpesaCallback` actions.
- Portals: `Guest/GuestController.php` (public home, listings, saved searches), `Guest/LandlordController.php` (become-landlord), `Searcher/*` (rentals, maintenance, move-out notices, assistant).
- Listing API: `routes/api.php:8-10` single `GET /listings` (`throttle:60,1`, no auth), `Api/ListingController.php`.
- Tenancy: `config/tenancy.php` (single-DB; `DatabaseTenancyBootstrapper` off), `TenantService`/`CreateOrganization` (registry + membership + subdomain, no DB provisioning), `Plan.has_dedicated_db/has_custom_domain` flags only.

## Landlord pain
- Low here — but `become-landlord` and billing must stay one-tap; an M-Pesa STK that fails silently loses a plan upgrade with no recourse path shown.

## Enterprise gap
- Dedicated DB + custom domain unwired; listing API has no keys/scopes/versioning/quotas/billing; no partner onboarding.

## Revamp proposal
1. Tier-gated provisioning in `CreateOrganization`: create DB (`MySQLDatabaseManager`), `tenants:migrate`, per-tenant bootstrapper flip, per-tenant media root, custom-domain attach + TLS validation. Single-DB stays the default.
2. Listing API as a product: `api/v1` versioning, hashed keys + scopes + quotas, subscription billing against `SubscriptionPayment`, OpenAPI docs, CDN caching. Keep the anonymous endpoint working (grandfathered, tighter throttle).
3. Billing UX: STK push with visible pending state + callback reconciliation + receipt; failed upgrades show "retry / contact support" with the attempt logged.

## Acceptance
- Enterprise tier provisions an isolated DB + custom domain from the plan upgrade without manual ops.
- Partner integrates against `api/v1` with a scoped key; quota breach returns a billable-upgrade hint, not a silent 429.
- Every billing attempt (success or fail) has a `SubscriptionPayment`/attempt record.

## Next
Series complete (01–16). Suggested build order: 06 + 08 (quick-add tenant, record rent) → 02 + 03 (wizard, shell) → 11 + 12 (issue logging, one-screen refunds) → 08-reconciliation + 13-datasets (M-Pesa matching, ageing/P&L) → 15 (approvals, immutable audit) → 04-branches + 16 (provisioning, API v1). Revisit `doc/enterprise-and-landlord-review.md` sequencing before cutting tickets.
