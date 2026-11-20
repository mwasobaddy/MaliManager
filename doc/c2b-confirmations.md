# C2B confirmations: rent-side Daraja pipeline (Project B)

Decided: unmatched M-Pesa rent is solved with a real C2B confirmation
pipeline, not a suspense ledger. Option A (recording lease-less payments
for later matching) was rejected: it creates reconciliation semantics to
maintain forever while leaving the actual headache — money arriving with
a wrong account number or no identifiable tenant — untouched.

## Why this is a project, not a widget

Every M-Pesa callback in the system today is a *subscription* STK
callback matched by `CheckoutRequestID` to a `PaymentAttempt`. There is
no rent-side Daraja surface at all: no C2B confirmation endpoint, no
validation, no account-reference matching, and rent `Payment` rows
require a lease at entry. The dashboard counter has nothing to count
until this pipeline exists, and building the counter first would be
theatre.

## Scope

1. **Tenant-side confirmation endpoint.** `POST` per-organization
   callback URL (same pattern as `tenant.billing.mpesa.callback`),
   CSRF-exempt, no auth session. Answers Safaricom's envelope always;
   failures are recorded, never bounced for retry.
2. **Validation.** Reject malformed payloads and unknown shortcodes at
   the edge with a warning log. Shortcode-to-organization resolution
   from stored landlord credentials, never from a client-supplied id.
3. **Lease matching by account reference.** Exact match on tenant
   account reference (unit/lease identifier) first; amount + date
   proximity as the documented fallback order. A match writes a normal
   `received` rent `Payment` — no new money representation.
4. **Unmatched queue with resolve/dismiss.** Anything unmatched lands in
   an `unmatched_payments` table (sender, amount, reference, received
   date, raw payload id) with a review list on the landlord dashboard:
   resolve assigns it to a lease (writes the `Payment`, marks resolved,
   audit-logged), dismiss archives it with a reason. The dashboard
   counter and alert read this table.

## Non-goals

- No suspense ledger, no lease-less `Payment` rows (the `lease_id`
  requirement stays).
- No auto-matching heuristics beyond the documented order until the
  queue shows which patterns actually recur.
- No tax computation off the back of it; the income summary stays
  gross-only.

## Decided (answering the open questions)

- **Shortcode ownership: landlord Paybills.** Money lands in each
  landlord's own account. Callback URLs and credential storage multiply
  per org; the endpoint resolves the organization from its own
  shortcode mapping, never from client input.
- **Reference format: landlord-configurable.** Each landlord chooses
  what their tenants type (unit number, lease code, or phone) in their
  payment settings; matching reads that configuration. Defaults to the
  unit number.
- **Resolve semantics: auto-retry + manual.** A nightly job re-runs the
  documented match order over the queue as new leases appear; whatever
  remains is human-matched with an audit trail.
- **Validation depth: full signature verification.** Strongest
  guarantee; the integration work is the point of making this a
  project rather than a widget.
