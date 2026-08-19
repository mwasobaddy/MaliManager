---
paths:
  - 'app/Http/Controllers/Auth/**'
---

# Auth

## Email+OTP login overrides Fortify; OTP is single-use with 10-min TTL
Login is email+OTP only. POST /login (in routes/web.php) overrides Fortify's AuthenticatedSessionController (web.php loads after Fortify routes). It creates a user if the email is unknown, issues a 6-digit OTP (10-min TTL), and stores `login.email` in the session. OTP reuse: OtpService::issue() reuses the latest usable code; verify() marks it used and sets email_verified_at. Un-onboarded users land on onboarding.show.

## Auth controllers validate via Form Requests
Auth input is validated through Form Requests in app/Http/Requests/Auth/: LoginRequest (email), OtpVerifyRequest (6-digit code), OnboardingCompleteRequest (conditional account_type/org rules; account_type only required when the user owns no organization). Controllers type-hint the request and use ->validated(). Frontend mirrors these rules to gate submit buttons and show inline errors.

## AuthLanding drives all post-auth redirects
Post-login and post-onboarding redirects go through App\Support\AuthLanding::for(): un-onboarded → onboarding.show; org owners → their tenant subdomain (property-count aware: no properties → /properties/create; exactly one property → /properties/{slug}, skipping the picker; two or more → /properties picker); occupants → central dashboard. Onboarding.complete and OTP/socialite verify all use it. SESSION_DOMAIN=.malimanager.test shares the session across central + tenant subdomains.
