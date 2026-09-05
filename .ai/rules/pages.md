---
paths:
  - resources/js/pages/welcome.tsx
---

# Pages

## Welcome page = branded marketing landing
The home page (welcome.tsx) is the marketing landing converted from the root index.html mockup. Use Warm Earth tokens only (primary terracotta CTAs, border-border cards, status-success for positive data, brand-accent for "Live"/in-progress glow). Utilities live in resources/css/app.css: .grid-texture and the --animate-fade-in-up keyframe (hero entrance); prefer these over arbitrary inline values. Marketing CTA links point to login() since there is no register route; when auth.user is set, show Dashboard instead.
