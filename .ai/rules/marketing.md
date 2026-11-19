---
paths:
  - 'resources/js/**'
  - 'resources/css/**'
---

# Product UI, never marketing UI

Product surfaces read as utility unless the user explicitly asks for
marketing treatment. The only approved marketing surfaces are the ones
the user has named: the welcome landing and the auth brand panel.
Everything else that shines, glows or pitches is a bug.

## Alerts and critical information

- Status tokens plus plain text. No gradients, glass, glow, shine,
  illustration, or entrance animation on anything the user must act on.
- Actionable text beats a chart: the alert says what happened, how bad
  it is, and the next step where one exists.
- Never reuse a promo component for an alert, and never reuse an alert
  component for upsell. One shared "fancy card" trains the reader to
  dismiss both.

## Upsell and limits

- Upgrade and limit-hit surfaces may use brand treatment, but they stay
  labeled as upgrades and never block or mimic system status.
- A limit message names the limit and the action. It does not sell the
  plan with feature lists.

## Empty states and copy

- Empty states offer the next step (at most one primary action), not a
  pitch.
- Product copy states facts: no exclamation-heavy or salesy language, no
  manufactured urgency.
- Missing data renders as an em dash or an honest "not yet" note — never
  zeros that imply measurement, never a flatlined chart.
