---
paths:
  - 'app/Http/Controllers/Auth/**'
---

# Auth

## Email+OTP login overrides Fortify; OTP is single-use with 10-min TTL
Login is email+OTP only. POST /login (in routes/web.php) overrides Fortify's AuthenticatedSessionController (web.php loads after Fortify routes). It creates a user if the email is unknown, issues a 6-digit OTP (10-min TTL), and stores `login.email` in the session. OTP reuse: OtpService::issue() reuses the latest usable code; verify() marks it used and sets email_verified_at. Un-onboarded users land on onboarding.show.
