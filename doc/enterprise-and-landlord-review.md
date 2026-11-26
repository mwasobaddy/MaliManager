# Enterprise vs Local Landlord Review

Date: 2026-11-26. Scope: where the system as built (`project.md` + routes + requests + services) overshoots a small landlord/caretaker, and where it undershoots an enterprise operator. Each item cites the file/route that proves it.

Personas assumed:
- **Local landlord/caretaker**: 1 property, 5–10 units, phone-first, tenants often phone-only (no email), rent in cash or M-Pesa, caretaker does the fieldwork on a shared phone.
- **Enterprise**: 500+ units across branches, finance + auditor + branch-manager roles, SLA-bound maintenance, reconciled M-Pesa at volume, portfolio reporting, compliance-grade audit, partner/listing API consumers.

---

## Part A — Too complicated for a landlord / caretaker

### A1. Caretaker cannot record rent (the daily job #1)
- **Evidence**: `app/Support/DefaultSubRoles.php:92-120` — caretaker bundle has occupant/unit/lease/maintenance/staff keys but no `PaymentManage`/`ExpenseManage`/`DepositManage`; `routes/tenant.php:484-499` gates all of `/payments*` on `payment.manage`.
- **Why it hurts**: caretaker collects cash/M-Pesa daily but must phone the owner or share the owner login to log it.
- **Fix**: add a narrow `payment.record` (create-only, no edit/delete, scoped to delegated properties) to the caretaker bundle, plus a one-tap "Record cash / M-Pesa" form defaulting to today.

### A2. Payment form speaks `lease_id`, not person / house / M-Pesa code
- **Evidence**: `StorePaymentRequest` requires `lease_id`; `PaymentController` builds a `leases()` picker; the form asks for lease + amount + currency + period dates + method + reference + status + notes.
- **Why it hurts**: caretaker thinks "Hse 3, 0712…, M-Pesa ABC123", not "lease 42". Period/status/currency fields invite errors on a small screen.
- **Fix**: search by occupant/unit/phone/M-Pesa ref, auto-resolve the active lease; prefill `paid_on=today`, `status=received`, `currency=KES`; collapse period/notes behind "More".

### A3. Add-tenant demands email + full lease in one shot
- **Evidence**: `app/Http/Requests/Tenant/StoreOccupantRequest.php:23-31` — `email` required, `unit_ids` required; lease extras/agreement optional but presented together in `occupant-form-fields.tsx`.
- **Why it hurts**: many tenants are phone-only; one big Person+User+Occupant+Lease transaction means a validation failure = no tenant recorded at all.
- **Fix**: "Quick add" with only `first_name + phone + unit + rent_amount` (defaults: monthly, KES, move-in now); email/national ID/agreement deferred to an "complete profile" nudge.

### A4. Multi-step setup before any value
- **Evidence**: `OnboardingController` (`firstAsset → plan`), `Tenant/SetupController` (`setup/team`, `setup/review` with `requireAsset + requirePlanChoice`), `SetupProgress` stepper; tenant routes sit behind `has-property` (`routes/tenant.php:107`).
- **Why it hurts**: a phone user with one plot wants "add plot → add houses → add people → record money". Plan/team/review reads as office paperwork; drop-off means paper wins.
- **Fix**: single wizard (Property → paste unit list → tenants → done); demote plan/team/review to a skippable post-setup checklist.

### A5. Building-vs-land-parcel choice up front
- **Evidence**: `resources/js/pages/setup/first-asset.tsx` Building vs Land parcel cards; `routes/tenant.php:447-483` keeps `/land-parcels*` outside the `has-property` group.
- **Why it hurts**: jargon to a landlord with "my plot with rooms"; a wrong pick strands them in the wrong module.
- **Fix**: default to Building; only offer "is this just land?" when zero units exist after creation.

### A6. Single-property orgs pay multi-property navigation tax
- **Evidence**: occupant/lease URLs are all `/{property:slug}/…` (`routes/tenant.php:218-299`); `AuthLanding` + property picker + cross-subdomain bounces (`AuthLanding.php`).
- **Why it hurts**: slugs in every URL, picker modal, and domain hops break the mental model on a phone; a 1-property user never needs any of it.
- **Fix**: when `properties + parcels == 1`, land directly on that dashboard, hide the picker/slug, keep `/properties` as Advanced.

### A7. Deposit refund is a 6-state, 3-role workflow
- **Evidence**: `app/Enums/DepositRefundStatus.php:16-21` (pending → awaiting_inspection → in_review → approved/rejected → paid); `routes/tenant.php:113-141` splits request (`lease.manage`) vs decide/pay (`deposit.manage`) vs review (`deposit.review`).
- **Why it hurts**: reality is "deduct broken window 2k, send rest via M-Pesa". Caretaker cannot even open it.
- **Fix**: one screen (`deposit − photo deductions = payout`) with single owner Approve-&-Pay; hide the inspection/review states behind a feature flag.

### A8. Password-to-delete freezes the field
- **Evidence**: `Destroy*Request` classes require `current_password` (e.g. `DestroyOccupantRequest`, `DestroyUnitRequest`, `DestroyStaffRequest`); `ConfirmDeleteOverlay` enforces it; `project.md §10`.
- **Why it hurts**: a caretaker typo (wrong house/amount) cannot be fixed without the owner's password on a shared phone.
- **Fix**: keep passwords for owner permanent-delete only; let delegated staff soft-archive with an audit trail and self-serve restore via the existing `/trash` (`routes/tenant.php:558-567`).

### A9. Utilities module is a metered-rates engine for a shared tap
- **Evidence**: `resources/js/pages/organisation/utilities/index.tsx` (readings + charges + setup + reading grid); `StoreUtilityChargeRequest` requires `unit_ids`; `routes/tenant.php:181-208` (bulk readings, charges CRUD, export); dual gates (`utility.manage` vs `unit.manage`).
- **Why it hurts**: 5–10 units usually split water/token as a fixed add-on to rent. Rates, assignments, bulk grid, CSV export overwhelm.
- **Fix**: default "Add bill to rent (fixed / split equally)"; gate metered rates + bulk reading behind an "I have meters" toggle.

### A10. Day-one exposure of maintenance states, AI keys, templates, roles
- **Evidence**: `StoreMaintenanceRequest` requires title+description+IDs+priority+photos; maintenance index carries status+priority filters and `allowed_transitions`; `reporting-ai.md` BYO-key flow (`ai_settings` provider/model/key/allow-lists); `SubPermissionKey` (~60 keys) plus `/roles`, `/audit`, `/assistant`, `/drafting`, `/agreement-templates` routes.
- **Why it hurts**: the caretaker log is "Hse 3 tap leaking + photo". Title/description/priority + 5-state `opened → … → closed` + pasting an AI key + TipTap `{{placeholders}}` guarantees abandonment.
- **Fix**: "Log issue" = photo + unit + voice/text, default `priority=medium`; progressive disclosure — hide AI/Templates/Roles/Audit/Trash/Reports behind "Advanced" until the org grows.

---

## Part B — Landlord-grade where enterprise is needed

### B1. Dedicated DB + custom domain are flags, not provisioning
- **Evidence**: `config/tenancy.php:46-62` (`DatabaseTenancyBootstrapper` commented out; filesystem bootstrapper disabled); `TenantService` is single-DB only (registry row + membership, no DB creation); `Plan` only carries `has_dedicated_db`/`has_custom_domain` booleans; `productionNotes.md` marks Enterprise DB "not yet implemented".
- **Why it fails**: no data isolation, per-client backup/restore, noisy-neighbour protection, or vanity-domain certs; one shared DB means one migration/prune affects everyone.
- **Uplift**: tier-gated provisioning in `CreateOrganization` (create DB via `MySQLDatabaseManager`, run `tenants:migrate`, flip per-tenant bootstrapper), per-tenant media root, automated custom-domain attach + TLS validation.

### B2. No branch / region / portfolio hierarchy
- **Evidence**: zero `branch_id` hits in `app/`; `Delegation` is flat per-asset (polymorphic `delegatable_type`); `ReportService` loops all properties org-wide with no branch grouping; property picker is org-flat.
- **Why it fails**: multi-branch firms need branch managers, branch P&L, branch-scoped staff/auditor views; per-property delegation alone means hundreds of rows and no roll-up.
- **Uplift**: add `branches` (org → branch → property), make delegation branch-inheritable, add `branch_id` to every report/dashboard/arrears query.

### B3. RBAC too coarse — no maker-checker, no auditor
- **Evidence**: `SubPermissionKey`: `payment.manage` / `expense.manage` are single keys (no create/approve/void split); `DepositRefundPolicy` has decide/review/pay but no dual-approval enforcement; `ReportController` grants all reports on any one `*.manage` key; `BillingController` is owner-only (finance locked out).
- **Why it fails**: auditors need read-only; finance needs preparer ≠ approver on refunds/payouts/write-offs; the current model forces over-privileged owners.
- **Uplift**: split money keys (`payment.create/approve/void`), add read-only `auditor` sub-role, enforce two-person approval on refunds above a threshold, allow delegated `billing.view` for finance.

### B4. Audit log is 180-day, mutable, CSV-only
- **Evidence**: `routes/console.php:12` (`activity:prune --days=180 --daily`); tenant + platform `AuditController::export` stream CSV with no hash/signature; no retention-per-tier config.
- **Why it fails**: regulators expect multi-year immutable trails; a 6-month prune destroys evidence; unsigned CSV is repudiable.
- **Uplift**: append-only (hash-chained, no update/delete) audit store, configurable retention (e.g. 7y Enterprise), signed PDF+CSV export with checksum, auditor read-only API.

### B5. Rent M-Pesa is manual — only subscription billing is wired
- **Evidence**: `app/Actions/Billing/HandleMpesaCallback.php` + `InitiateMpesaPayment` write `SubscriptionPayment` (org→platform), not `Payment` (tenant rent); rent intake is the manual `CreatePayment` form with free-text `reference`, no C2B listener, no receipt-number uniqueness, no suspense queue.
- **Why it fails**: reconciling hundreds of monthly M-Pesa lines by hand-typed references guarantees misallocation, duplicates, month-end chaos.
- **Uplift**: C2B/paybill listener writing `payments(status=pending)` keyed by `MpesaReceiptNumber UNIQUE`, auto-match engine (amount+phone+lease), suspense-queue UI, idempotent retry, B2C refunds tied to `DepositRefund`.

### B6. Reporting is single-org, single-currency, no money datasets
- **Evidence**: `ReportService::TYPES = occupancy, leases, expenses, maintenance, market` — no rent-collection/arrears-ageing/P&L/branch dataset; `expenses()` casts `sum('amount')` to float and hardcodes `' KES'`; `PlatformOverviewService` notes "Money is KES… multi-currency needs per-currency buckets".
- **Why it fails**: a portfolio with USD/KES rents gets meaningless summed totals and no collection-rate/arrears-ageing/board pack.
- **Uplift**: add `rent_collection`, `arrears_ageing`, `property_pl` datasets; per-currency buckets + FX table; scheduled portfolio board-pack (PDF) delivery.

### B7. Public Listing API is one anonymous endpoint, not a product
- **Evidence**: `routes/api.php:8-10` (single `GET /listings`, `throttle:60,1`, no auth); `Api/ListingController` plain paginate, no version prefix; `project.md §8.15` lists keys/quotas/billing as planned-not-built.
- **Why it fails**: no monetizable tiers, partner keys/scopes, versioning/OpenAPI, or aggregator-grade throttling/caching.
- **Uplift**: `api/v1` versioning, hashed API keys + scopes + quotas, subscription billing, OpenAPI docs, CDN-friendly caching.

### B8. Maintenance has no SLA / escalation engine
- **Evidence**: `MaintenanceRequest` has only `status/priority/assigned_to` (no `sla_due_at/escalated_at`); scheduler (`routes/console.php`) has rent/lease/billing/digest jobs but zero maintenance-SLA job; `ReportService::maintenance()` reports counts + avg days only.
- **Why it fails**: SLA-bound enterprise needs response/resolve timers by priority, auto-escalation, breach dashboards; averages after the fact are not SLA management.
- **Uplift**: SLA policy per priority/asset, `sla_due_at` + `escalation_level` columns, scheduled escalator with assignee/owner nudges, breach report.

### B9. Notifications are synchronous sends — no centre, no log
- **Evidence**: `project.md §8.11` ("in-app DB notifications … not built"); `NotificationService` uses synchronous `Mail::send` (not queued `Notification`s); no `notifications` table / `Notifiable` in `app/Models`; no delivery-status log.
- **Why it fails**: rent-reminder/digest fan-out at volume times out in cron with no retry and no "who was told what" audit or preference centre.
- **Uplift**: DB notification centre + queued mail/SMS drivers, per-event template/channel preferences, delivery log linked to audit, retry/backoff via queue.

### B10. Queue defaults to database — fan-out runs inline in cron
- **Evidence**: `config/queue.php:16` default `database`; `PlatformOverviewService::operations()` surfaces `sync (no workers)` as a state; notification sends run synchronously inside `billing:remind` / `rent:remind-overdue` daily jobs.
- **Why it fails**: daily reminder/digest runs exceed cron windows at volume; one slow SMS gateway stalls the batch; partial failures are unrecoverable with no dead-letter triage.
- **Uplift**: mandate Redis/SQS + supervised workers in prod; convert fan-out to idempotent `ShouldQueue` jobs (unique per lease+period); failed-job dashboard with retry; rate-limited SMS batching.

---

## Suggested sequencing

1. **Landlord quick wins (highest retention leverage)**: A1 + A2 (caretaker rent recording), A3 (quick-add tenant), A10 (hide Advanced).
2. **Landlord structural**: A4 + A6 (single-wizard setup, single-property mode), A7/A8/A9 (simplify refund, delete, utilities).
3. **Enterprise revenue-unblocking**: B5 (M-Pesa rent reconciliation) + B6 (money reporting) — these decide 500-unit deals.
4. **Enterprise compliance**: B3 + B4 (maker-checker, immutable audit) + B1 (dedicated DB provisioning).
5. **Enterprise scale products**: B2, B7, B8, B9, B10.
