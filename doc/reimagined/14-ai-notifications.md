# 14 — AI Assistant & Notifications (reimagined)

Source review: `doc/enterprise-and-landlord-review.md` §A10, §B9, §B10.

## Current state — files and how they link
- AI: `Tenant/AssistantController.php`, `Searcher/SearcherAssistantController.php` (own-data scoped), `Platform/AssistantController.php`, `Tenant/DraftingController.php` (content studio), `Tenant/AiSettingsController.php` (BYO keys, encrypted, feature/member/role allow-lists), `SaveAiSettings/DeleteAiSetting` actions, `reporting-ai.md` (R0–R3: ask-your-data, triage incl. vision, drafting, predictive flags, weekly digest, renewal suggestions, token limits). Routes under `lease.manage_templates` + `ai.use`.
- Notifications: in-app DB **not built** (topbar stub); `NotificationService` sends synchronously via `Mail::send` (not queued `Notification`s); no `notifications` table; cron fan-out runs inline (`billing:remind`, `rent:remind-overdue`, `ai:send-digest` in `routes/console.php`); `QUEUE_CONNECTION` defaults to `database` (`config/queue.php:16`).

## Landlord pain
- AI key setup (provider/model/key/allow-lists) is shown before any landlord value; digest and drafting might as well not exist for them.

## Enterprise gap
- No notification centre, no per-event template/channel preferences, no delivery log, no retry/backoff. At volume, inline cron sends time out with no proof of "who was told what".

## Revamp proposal
1. AI: hide key setup until asked — orgs start on a platform default (capped); the AI settings page is already out of the simple sidebar (Pro lens, see 03), keeping BYO keys there is enough. Surface only two landlord-visible AI features (triage suggestion in 11, renewal suggestion in 07) plus the weekly digest.
2. Notifications: DB notification centre + queued mail/SMS drivers; per-event template/channel preferences; recipient-channel routing (existing `NotificationService` rule: recipient's channel, SMS needs phone or fall back to email); delivery log linked to audit (feeds 15).
3. Queue: mandate Redis/SQS + supervised workers in prod; convert all fan-out to idempotent `ShouldQueue` jobs (unique per lease+period); failed-job dashboard with retry; rate-limited SMS batching.

## Acceptance
- Landlord gets rent reminders + digest with zero AI configuration.
- Every fan-out send has a delivery record; a failed SMS run is retryable from the dashboard.
- Token usage stays visible per user with monthly caps enforced.

## Next
Work on `15-roles-audit-trash.md` — permissions, proof, and recovery.
