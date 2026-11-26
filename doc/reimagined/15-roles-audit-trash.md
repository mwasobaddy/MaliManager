# 15 — Roles, Audit & Trash (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §A1, §A8, §B3, §B4.

## Current state — files and how they link
- Roles: `app/Support/DefaultSubRoles.php` (landlord=all, caretaker bundle, agent bundle; additive-only `syncBundlesFor`), `SubPermissionKey` enum, `User::hasSubPermission()`, `Tenant/StaffController.php` + `SubRoleController.php` + `Create/Update/DeleteStaff/SubRole` actions, `Platform/RolesController.php` (central roles, built-ins protected, `RequirePassword` on update).
- Request/authorisation: `sub-permission:*` middleware, `Destroy*Request` (`current_password`), `Platform/OrganizationController::impersonateOwner` + `ImpersonationController::stop` (central-only URI, not behind `admin` — see AGENTS.md).
- Audit: `Tenant/AuditController.php` + `Platform/AuditController.php` (index/export CSV), `LogsTenantActivity` / `LogsPlatformActivity` traits, `activity:prune --days=180` (`routes/console.php:12`).
- Trash: `Tenant/TrashController.php` + `Platform/TrashController.php`, `TrashModules` registry, tiered scope (`DeletionTier`), severities documented in `.ai/rules/services.md`.

## Landlord pain
- Caretaker bundle lacks any money participation; password-on-every-delete punishes typos on shared phones; roles/audit/trash pages are exposed before they are needed.

## Enterprise gap
- Coarse money keys (no create/approve/void), no auditor read-only role, no two-person approval, finance locked out of billing (owner-only), 180-day mutable audit with unsigned CSV.

## Revamp proposal
1. New narrow keys: `payment.record` (create-only, delegated scope → caretaker), `expense.approve`, `deposit.approve`, `billing.view` (finance without owner). Auditor sub-role: read-only everything incl. exports.
2. Two-person approval on refunds/payouts/voids above threshold (pairs with docs 08/10/12).
3. Soften delete: delegated staff soft-archive with trail + self-serve restore; passwords stay for owner permanent-delete only.
4. Audit: append-only hash-chained store, retention per tier (7y enterprise), signed PDF+CSV export, auditor read-only API.
5. Security policy surface: **built**. Staff → Roles hosts the policy card (staff two-factor requirement + Simple/Pro switch, `PUT /security-policy`, owner-only enforced in `UpdateOrganizationSecurity`), and the sidebar "Show all features" item flips the same flag.

## Acceptance
- Caretaker records rent and drafts deductions but can never approve, pay, or permanently delete.
- Refund above threshold with one approver is rejected by policy test.
- Audit rows are uneditable (no update/delete path) with retention enforced per tier.
- Owners switch Simple/Pro from Roles or the sidebar; staff never see the switch.

## Next
Work on `16-platform-api.md` — the platform surface that ties it all together.
