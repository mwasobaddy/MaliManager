# 02 — Onboarding & Setup (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §A4, §A5.

## Current state — files and how they link
- Backend: `app/Http/Controllers/OnboardingController.php` (`firstAsset → plan`), `app/Http/Controllers/Tenant/SetupController.php` (`team → review`, both `requireAsset + requirePlanChoice`), `app/Actions/Onboarding/CompleteOnboarding.php`, `BecomeLandlord.php`, `app/Actions/Tenants/CreateOrganization.php`, `app/Support/SetupProgress.php` (stepper), `AuthLanding` (post-login routing).
- Frontend: `resources/js/pages/setup/first-asset.tsx`, `property.tsx`, `parcel.tsx`, `team.tsx`, `plan.tsx`, `review.tsx`, `become-landlord.tsx`, `auth/onboarding.tsx`.
- Gates: `onboarded` middleware (dashboard only), `has-property` (`EnsureOrganizationHasProperty`) blocks tenant routes until the first asset exists.

## Landlord pain
- 5+ steps (asset → property/parcel form → team → plan → review) before any value; plan/team/review read as office paperwork on a phone. Building-vs-land choice up front strands users in the wrong module.

## Enterprise gap
- No bulk provisioning path: an operator onboarding 20 buildings needs the same wizard 20 times. No import-first onboarding (units/tenants CSV at setup), no delegated setup (finance fills billing while ops fills properties).

## Revamp proposal
1. Single wizard: `Property → paste unit list → tenants → Done`. Demote plan/team/review to a skippable post-setup checklist ("You can do this later").
2. Default to Building; ask "is this just land?" only when zero units are entered (kills the A5 fork).
3. Add setup imports: units CSV + tenants CSV as first-class wizard steps (reuse `ImportUnits` action pattern from `app/Actions/Properties/`).
4. Track wizard drop-off (step reached vs completed) so we can see where landlords quit.

## Acceptance
- A landlord completes setup in ≤ 4 screens and can record first rent the same session.
- Skipping plan/team never blocks the dashboard; checklist reminds, never gates.
- CSV import of 50 units + tenants works from inside the wizard.

## Next
Work on `03-navigation-shell.md` — once inside, the shell (top bar, picker, slugs) is the next friction layer.
