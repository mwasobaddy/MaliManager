# 01 — Auth & Login (reimagined)

Source review: `doc/enterprise-and-landlord-review.md`.

## Current state — files and how they link
- Backend: `app/Http/Controllers/Auth/LoginController.php`, `OtpController.php`, `SocialiteController.php`, Fortify (prefix `auth`, `config/fortify.php`), `bootstrap/app.php` (rate limiting, `EnsureUserIsActive` suspend check → `auth/suspended`).
- Frontend: `resources/js/pages/auth/login.tsx` (combined login/signup), `otp.tsx`, `two-factor-challenge.tsx`, `verify-email.tsx`, `forgot-password.tsx`, `reset-password.tsx`, `confirm-password.tsx`.
- Link chain: `routes/auth.php` (loaded by `routes/web.php`) → Fortify actions / custom controllers → `AuthLanding` (`app/Support/AuthLanding.php`) decides central `dashboard` vs tenant subdomain vs setup; `onboarded` middleware gates only `dashboard` (`routes/web.php`).

## Landlord pain
- Login is fine; the cliff comes right after (see 02). One real gap: OTP/email+password choice is presented up front while many caretakers share one phone — no "remember this device / stay signed in on my phone" clarity, and suspend handling (`suspended` page) gives no "contact owner" path.

## Enterprise gap
- No SSO/SAML, no enforced 2FA policy per org, no session/device management, no audit of login anomalies. `EnsureUserIsActive` covers suspension but there is no brute-force alerting or admin visibility into auth events.

## Revamp proposal
1. Keep the combined login/signup page; add explicit "This is my own phone — stay signed in" vs shared-device hint.
2. Add per-organization auth policy (owner setting): optional 2FA enforcement for staff, session timeout.
3. Add auth event logging (login, OTP, 2FA, suspend redirect) into the audit trail (feeds 15).
4. Suspended page: show who to contact (owner name/phone) + "request reactivation" button.

## Acceptance
- Single-device caretaker can stay signed in 30 days without re-OTP; shared-device user can opt out.
- Owner can toggle "require 2FA for staff"; staff without 2FA are blocked with a setup prompt.
- Every auth event appears in tenant audit.

## Next
Work on `02-onboarding-setup.md` — the first-run funnel is where small landlords are actually lost.
