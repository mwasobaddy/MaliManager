---
paths:
  - app/Support/Ai/AssistantService.php
---

# Ai

## AI broadcast tools draft before sending
The send_rent_reminders tool lists recipients by default and only sends with confirmed=true, each send honoring the recipient channel and the per-lease throttle. Tenant-facing AI sends always need this two-step shape — no other assistant tool sends anything today.
