# 08 — Payments & Reconciliation (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §A1, §A2, §B5, §B6.

## Current state — files and how they link
- Tenant rent: `app/Http/Controllers/Tenant/PaymentController.php` (index/create/store/edit/update/destroy/receipt), `app/Actions/Payments/CreatePayment.php` (manual form, free-text `reference`), `StorePaymentRequest` (`lease_id` required), `organisation/payments/index.tsx`, `form.tsx` (lease select + amount/currency/period/method/status), `receipt.tsx`.
- Platform billing (separate pipe): `Tenant/BillingController.php`, `app/Actions/Billing/InitiateMpesaPayment.php`, `HandleMpesaCallback.php`, `MpesaCallbackController.php` → writes `SubscriptionPayment`, not rent `Payment`.
- Arrears: `RentArrearsService`, `rent:flag-overdue` + `rent:remind-overdue` cron (`routes/console.php`), `SendRentReminder` / `SendBulkRentReminders` actions.
- Money rules: `decimal(14,2)` + `decimal:2` casts, bcmath (`.ai/rules/money.md`).

## Landlord pain
- Caretaker (who collects) has no payment key at all (doc 15 fixes the grant); the form demands `lease_id` plus period/currency/status fields instead of person/house/M-Pesa code.

## Enterprise gap
- No C2B listener for rent, no receipt uniqueness, no suspense/unmatched-payments queue, no auto-match (amount+phone+lease), no B2C refund link to deposit refunds. Single-currency (`KES` hardcoded in reporting) reconciliation at 500 units/month is unworkable.

## Revamp proposal
1. **Resolve-don't-select**: payment form searches occupant/unit/phone/M-Pesa ref and auto-resolves the active lease; prefill `paid_on=today`, `status=received`, `currency=KES`; collapse period/notes behind More.
2. **Narrow grant**: `payment.record` (create-only, delegated properties) for caretakers — paired with the role work in 15.
3. **Reconciliation core** (the enterprise bet): C2B listener writes `payments(status=pending)` keyed by `MpesaReceiptNumber UNIQUE`; auto-match engine proposes lease links; suspense queue UI for the unmatched; idempotent retry; B2C refunds tied to `DepositRefund`.
4. Per-currency buckets + FX table; arrears ageing (`current/30/60/90+`) as a first-class dataset (feeds 11).

## Acceptance
- Caretaker records cash/M-Pesa in ≤ 30 seconds without knowing what a lease is.
- Duplicate M-Pesa callbacks never double-post (unique receipt constraint test).
- Finance clears a 500-line M-Pesa month with match-rate visible and suspense < 5%.

## Next
Work on `09-utilities.md` — the metering add-on to rent.
