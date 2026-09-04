# AI Feature File Inventory

A complete map of every file that implements, configures, or wires the
**bring-your-own-key (BYO)** AI layer in MaliManager. The platform never pays
for or stores LLM tokens — every feature resolves the caller's own credential
through `AiGateway`, calls Prism through that credential, and logs the dispatch
to `ai_usage_logs`.

> **Design note — provider routing:** `app/Enums/AiProvider::prismDriver()` maps
> each provider to a Prism driver. NVIDIA NIM only implements the OpenAI
> **Chat Completions** API (not the Responses API that Prism's `openai` driver
> uses), so NVIDIA is intentionally routed through Prism's `openrouter` driver
> with a `base_url` override pointing at NVIDIA. All other providers map to their
> native Prism driver.

---

## 1. Core (credential resolution & policy)

### `app/Enums/AiFeature.php`

The five individually-toggleable AI capabilities:
`ask_data`, `maintenance_triage`, `content_drafting`, `predictive_flags`,
`inspection_reports`. Each has a `label()` and the `options()` list used to build
the feature toggle UI. Stored as JSON strings in `ai_settings.features`.

### `app/Enums/AiProvider.php`

Every supported LLM provider (OpenAI, Anthropic, DeepSeek, OpenRouter, NVIDIA,
Gemini, Groq, Mistral, Ollama, Perplexity, xAI, Z, ElevenLabs, VoyageAI).

- `label()` — human label.
- `prismDriver()` — the Prism driver name used by `using()` (NVIDIA → `openrouter`,
  all others → their own name). **This is the single place that fixes the
  NVIDIA Responses-vs-Chat-Completions mismatch.**
- `baseUrl()` — default endpoint override (only NVIDIA sets one: the NIM URL).
- `suggestedModels()` — model dropdown suggestions per provider.

### `app/Support/Ai/AiGateway.php`

The central policy engine. `resolve(user, feature, ?organization)` returns a
`ResolvedAiCredential` or `null`:

1. **Personal key wins** — an `AiSetting` owned by the user.
2. **Organization key** — if the user passes `memberAllowed()` (allow-all,
   explicit `allowed_user_ids`, or matching `allowed_roles`).
3. **Fallback scan** — for occupant users with no membership row, scans org
   credentials whose allow-list names them.

- `usable()` = the setting enables the feature **and** is `withinMonthlyLimit()`.
- `withinMonthlyLimit()` — optional soft cap on tokens this calendar month
  (`monthly_token_limit`, 0 = unlimited).
- `log()` — writes one `AiUsageLog` row per dispatch (provider, model, tokens,
  duration, status, error).
- `canUse()` — used by `HandleInertiaRequests` to expose `auth.ai_enabled`.

### `app/Support/Ai/ResolvedAiCredential.php`

An immutable, **non-serializable** credential (API key stays server-side).

- `driver()` — Prism driver enum value.
- `requestConfig()` — builds `['api_key' => …, 'url' => …]` for
  `usingProviderConfig()`; `url` comes from `ai_settings.base_url` or the
  provider default (e.g. NVIDIA's NIM endpoint).

### `app/Models/AiSetting.php`

Polymorphic BYO credential (`owner` = `Organization` or `User`).

- `api_key` is `encrypted` at rest and `hidden`.
- `features` (array), `allow_all_members`, `allowed_user_ids`, `allowed_roles`,
  `monthly_token_limit`, `base_url`.
- `supportsFeature()` — whether this credential may power a given `AiFeature`.

### `app/Models/AiUsageLog.php`

One row per AI dispatch (no `updated_at`). Columns: `organization_id`,
`user_id`, `feature`, `provider`, `model`, `prompt_tokens`,
`completion_tokens`, `duration_ms`, `status`, `error_message`. Indexed by
org/user + `created_at` for the monthly-usage queries.

### Migrations

- `database/migrations/2026_08_26_015901_create_ai_settings_table.php` — the
  `ai_settings` table (nullable morph owner, provider/model/api_key, features,
  allow-list columns, monthly token cap, unique owner).
- `database/migrations/2026_08_26_015902_create_ai_usage_logs_table.php` — the
  `ai_usage_logs` table.
- `database/migrations/2026_08_26_074553_add_base_url_to_ai_settings_table.php`
  — adds the `base_url` column (proxies / self-hosted endpoints).
- `database/migrations/2026_08_26_022240_add_ai_priority_to_maintenance_requests_table.php`
  — adds `maintenance_requests.ai_priority`, populated by the AI triage job
  (separate from staff-controlled `priority`).

### `config/prism.php`

Prism provider config (URLs, API keys from env). Note there is **no `nvidia`
entry** — NVIDIA reuses the `openrouter` provider config, but the actual
endpoint is always supplied at call time via `usingProviderConfig(['url' => …])`.

---

## 2. AI features (the actual touchpoints)

Every one follows the same shape: `AiGateway::resolve → Prism call → gateway->log`.

### `app/Http/Controllers/Tenant/AssistantController.php`

**Ask-your-data assistant (org staff).** `ask()` resolves `AiFeature::AskData`
for the current tenant org and calls `Prism::text()` with **tools** backed by
`OrgInsightsService` (vacancy, leases, expenses, maintenance, properties) so the
LLM can never invent numbers. `withMaxSteps(5)`. `page()` exposes `enabled`.

### `app/Http/Controllers/SearcherAssistantController.php`

**Occupant-facing assistant (central domain).** Same tool pattern, but every
tool query is filtered by `person_id` / `raised_by` server-side so tenants only
see their own leases and maintenance requests.

### `app/Http/Controllers/Tenant/DraftingController.php`

**Content drafting studio.** `generate()` resolves `AiFeature::ContentDrafting`
and calls `Prism::text()` to produce `rent_reminder`, `lease_expiry_notice`,
`move_out_letter`, or `listing_description` from structured fields. Output is
copy-only. _(Fix: this file was missing `use Inertia\Response;`, which crashed
`page()` — now added.)_

### `app/Http/Controllers/Tenant/AiSettingsController.php`

**Organization AI configuration (owner-only).** `edit()` renders provider/model/
features/allow-list/masked key/monthly usage. `update()` persists the credential
(api key is write-only — empty = keep existing). `destroyKey()` removes it.
Monthly per-user usage comes from `AiUsageLog`.

### `app/Http/Controllers/Settings/PersonalAiController.php`

**User personal AI credential.** Identical shape to `AiSettingsController` but
owned by the `User`. A personal key takes precedence over any org key
(see `AiGateway` resolution order).

### `app/Services/AiRenewalService.php` (+ `app/Http/Controllers/Tenant/LeaseController.php`)

**AI lease renewal suggestion.** `suggest()` resolves `AiFeature::AskData` and
calls `Prism::structured()` with an `ObjectSchema` (`suggested_rent`,
`reasoning`). Triggered from `LeaseController::renewalSuggestion` (the expiring-
lease dialog on `leases/index.tsx`). Advisory only.

### `app/Jobs/TriageMaintenanceRequest.php` (+ `app/Models/MaintenanceRequest.php`)

**AI maintenance triage (queued).** Dispatched from
`MyMaintenanceController::store`. Resolves `AiFeature::MaintenanceTriage` for the
raiser and calls `Prism::structured()` to classify `priority`; if a photo is
attached it is sent as a vision input. Result lands in `ai_priority`
(advisory); staff decisions stay in `priority`. Failures are logged, never block.

### `app/Services/InspectionService.php` + `app/Http/Controllers/Tenant/InspectionController.php`

**AI inspection condition report.** `InspectionService::generateReport()`
resolves `AiFeature::InspectionReports`, loads the inspection's photos as vision
images, and calls `Prism::structured()` with a detailed `ObjectSchema`
(overall condition, per-area conditions/issues, recommendations). Stored on
`inspection.ai_report`. `InspectionController::generateReport()` wraps it in
try/catch → toast. _(Note: `InspectionController` also defines a duplicate
`reportSchema()` that is currently unused dead code.)_

### `app/Console/Commands/SendWeeklyDigest.php` + `app/Mail/OrgDigestMail.php` + `resources/views/mail/org-digest.blade.php`

**Weekly digest (scheduled).** The command computes deterministic weekly numbers
(`weeklyNumbers()`), builds recipients (owners + `maintenance.manage` staff), and
— only if the org has its own key — asks the LLM for a 3–4 sentence narrative
(`narrative()`) contrasting this week vs `*_prev`. `OrgDigestMail` renders the
numbers + optional narrative; the AI call uses `AiFeature::AskData`. Scheduled
weekly in `routes/console.php`.

### `app/Services/Reporting/OrgInsightsService.php`

**Backend for the assistant tools.** Read-only queries (vacancy summary, lease
summary, expense totals, maintenance backlog, properties) — the single source
of truth the LLM tools call, so responses stay factual. Not an LLM caller
itself, but every assistant answer depends on it.

---

## 3. Shared wiring (frontend flags & routes)

### `app/Http/Middleware/HandleInertiaRequests.php`

Shares `auth.ai_enabled` (resolved via `AiGateway::canUse(AskData, organization)`)
and `context.is_owner` to the frontend, gating AI UI.

### `resources/js/types/auth.ts`

Adds `ai_enabled?` and (separately) `canRaiseMaintenance` to the `Auth` type.

### `routes/tenant.php`

Registers `tenant.assistant.*`, `tenant.drafting.*`, `tenant.ai-settings.*`,
`tenant.inspections.*` (incl. `generateReport`), and `tenant.reports.*` — all
under the `lease.manage_templates` (or `inspection.manage`) sub-permission
groups.

### `routes/web.php`

Registers `searcher.assistant.*` (occupant assistant).

### `routes/settings.php`

Registers `settings.personal-ai.*` (personal credential CRUD).

### `routes/console.php`

Schedules `ai:send-digest` weekly (the digest command above).

### `resources/js/components/app-sidebar.tsx`

Shows **AI settings** to owners; **AI assistant / AI drafting / Reports** only
when `ai_enabled` (or owner); inspections entry under the property group when
`inspection.manage`.

### `resources/js/app.tsx`

Excludes `tenant/reports/print` and `tenant/inspections/show` from auth layouts
(standalone print / report views).

### `resources/js/layouts/settings/layout.tsx`

Adds the **AI** entry to the settings sidebar (personal key).

### `app/Services/DashboardService.php` (`predictiveFlags` / `expiringLeases`)

Rule-based detection (expense outliers, stale urgent maintenance, expiring
leases) — **not AI itself**, but surfaced on the dashboard as "Needs attention"
cards; the design intends the assistant to _explain_ them.

---

## 4. Frontend pages

### `resources/js/pages/tenant/assistant.tsx`

Org assistant chat. Posts to `tenant.assistant.ask`, renders `data.answer` as an
assistant bubble (or `data.error` / `data.detail` as an error bubble), with a
`.catch` for non-JSON failures. _(Fix: previously always rendered an error
bubble, so a successful reply showed "Something went wrong." — now branches on
`data.answer`.)_

### `resources/js/pages/tenant/drafting.tsx`

Drafting studio UI. Type/tone/field form → `tenant.drafting.generate`; renders
the returned `data.draft` with a copy button. Reads `?type=&…` query params for
pre-fill (used by the lease "Draft renewal" links).

### `resources/js/pages/tenant/ai-settings/index.tsx`

Owner config UI: provider/model/base_url/api_key (masked), feature toggles,
member allow-list, and this-month per-user usage table. Saves via
`tenant.ai-settings.update` / `destroy`.

### `resources/js/pages/searcher/assistant.tsx`

Occupant assistant chat (own-data scoped). Same pattern as the tenant assistant
but correctly handles `data.answer`.

### `resources/js/pages/settings/personal-ai.tsx`

Personal credential UI: provider/model/base_url/api_key, feature toggles,
personal monthly usage, remove key.

### `resources/js/pages/tenant/inspections/show.tsx`

Inspection detail. "Generate AI report" posts to `tenant.inspections.report`;
renders `inspection.ai_report` (overall condition, per-area conditions/issues,
recommendations) with print/PDF support.

---

## 5. Tests

- `tests/Feature/AiGatewayTest.php` — credential resolution, precedence, limits.
- `tests/Feature/AiProvidersTest.php` — `AiProvider::prismDriver()` mapping
  (incl. NVIDIA → `openrouter`), URL/base_url handling.
- `tests/Feature/AiFeaturesTest.php` — assistant ask (tool-backed), drafting,
  and error paths using `Prism::fake()`.
- `tests/Feature/DigestTest.php` — weekly digest numbers + AI narrative.
- `tests/Feature/InspectionTest.php` — inspection create + AI report generation.

(Reports tests cover the non-AI reporting module; excluded here as not LLM-
related.)

---

## 6. Design documentation

- `reporting-ai.md` — the original design blueprint for the BYO-AI layer
  (credential resolution order, `ai_settings` schema, feature plan).
