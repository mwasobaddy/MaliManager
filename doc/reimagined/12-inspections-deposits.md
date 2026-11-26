# 12 — Inspections & Deposit Refunds (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §A7.

## Current state — files and how they link
- Inspections: `app/Http/Controllers/Tenant/InspectionController.php` (index/create/store/show/report/destroy), `app/Actions/Inspections/CreateInspection.php`, `GenerateInspectionReport.php` (Prism vision → `ai_report` JSON), `organisation/inspections/` pages, photos under `{org}/{property}/inspections/`, chrome-free print page.
- Deposits: `Tenant/DepositRefundController.php`, `app/Actions/Deposits/*` (request, auto-request, deduction store/delete, submit-for-review, review, decide, mark-paid), `DepositRefundStatus` (6 states), routes split request (`lease.manage`) vs decide/pay (`deposit.manage`) vs review (`deposit.review`), `organisation/deposit-refunds/index.tsx`.

## Landlord pain
- Refund reality ("deduct 2k window, send rest via M-Pesa") drowns in pending → awaiting_inspection → in_review → approved/rejected → paid across three roles. Caretaker cannot participate.

## Enterprise gap
- No dual-approval threshold, no B2C payout link, no deduction-photo evidence standard, no deposit-ageing (deposits held per lease over time).

## Revamp proposal
1. One refund screen: `deposit − photo deductions = payout`, single owner Approve-&-Pay; hide inspection/review states behind a per-org "formal inspection flow" toggle (default off under 50 units).
2. Require photo per deduction line (already supported) and bind payout to B2C when 08's payment rails land.
3. Refunds above threshold require two-person approval (pairs with 15's `deposit.approve`).
4. Inspections: keep the AI report, but trigger it from the refund deduction flow too ("generate from these photos").

## Acceptance
- Standard refund completes in one sitting by one owner; formal-flow orgs keep all six states.
- Every deduction has photo evidence; every payout links to a payment record.
- Caretaker can draft deductions but never approve or pay.

## Next
Work on `13-reports-analytics.md` — turning all this operational data into decisions.
