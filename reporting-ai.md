# Reporting & AI — Design Blueprint

How MaliManager's Reporting & AI layer will work: a **bring-your-own-key (BYO)**
AI assistant plus structured reporting, built on [Prism PHP](https://prismphp.com)
so the platform never pays for or stores LLM credentials.

---

## 1. Core idea

Every organization (and optionally each individual user) supplies their own
LLM API key. The platform provides the _features_; the org provides the
_intelligence budget_. This keeps multi-tenancy cheap, avoids per-token billing
infrastructure on our side, and gives owners full control over which models
touch their data.

**Key resolution order:** personal key → organization key → feature hidden with
a "connect your AI" setup prompt.

## 2. Credentials & access control

### Storage

New `ai_settings` table:

| Column                    | Purpose                                                                                   |
| ------------------------- | ----------------------------------------------------------------------------------------- |
| `owner_type` / `owner_id` | `Organization` or `User` (polymorphic)                                                    |
| `provider`                | `openai` \| `anthropic` \| … (Prism enum value)                                           |
| `model`                   | e.g. `gpt-4o`, `claude-sonnet-4`                                                          |
| `api_key`                 | **encrypted at rest** (Laravel `encrypted` cast); never returned to the client after save |
| `allowed_features`        | JSON array of feature keys this key may be used for                                       |
| `monthly_token_limit`     | Optional soft cap; 0 = unlimited                                                          |

### Who may use AI

- The organization owner enables AI in Settings → AI, pastes their key, picks
  provider + model, and selects which **roles / individual users** get access.
- A user can add a **personal key** in Profile → AI; it takes precedence over
  the org key.
- Server-side gate: an `AiGateway` class resolves the effective credential for
  (`user`, `feature`) and returns `null` when none applies — the frontend then
  hides AI affordances entirely.

### Usage logging

Every call writes an `ai_usage_log` row: user, organization, feature, model,
prompt/completion tokens, duration, status. Owners see a usage dashboard in
Settings → AI ("who spent what"), since they are paying for it. The optional
`monthly_token_limit` is enforced before dispatch.

## 3. Technical integration — Prism PHP

[Prism](https://prismphp.com) (MIT, Laravel-native) abstracts OpenAI/Anthropic/
others behind one fluent API and supports **per-request credential overrides** —
exactly what BYO-key needs:

```php
$response = Prism::structured()
    ->using($setting->providerEnum, $setting->model)
    ->usingProviderConfig(['api_key' => decrypt($setting->api_key)])
    ->withSchema($schema)
    ->withPrompt($prompt)
    ->asStructured();
```

No vendor lock-in: switching provider is a settings dropdown, not a rewrite.
All AI calls run through one `App\Support\Ai\AiGateway` that handles credential
resolution, usage logging, limits, timeouts, and error toasts.

---

## 4. Features

### Phase 1

| Feature                     | What it does                                                                            | How                                                                                                                                                                                                                                             |
| --------------------------- | --------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Ask-your-data assistant** | Org-scoped chat: "Which units are vacant?", "Total expenses per property this quarter?" | Prism **tools** map natural language to safe, pre-built query tools (vacancy, arrears-by-property, expense-totals, lease-expiry). The model calls tools; results come from our SQL — the LLM never writes queries, so injection risk stays zero |
| **Maintenance triage**      | On request creation: suggested priority + category + summary                            | Structured output over title/description; staff confirm or override — suggestion stored as metadata                                                                                                                                             |
| **Content drafting**        | Occupant notices, rent reminders, listing descriptions                                  | Text generation with tone/length options; copy-to-clipboard only (never auto-send)                                                                                                                                                              |
| **Predictive flags**        | Expense anomaly detection + rent-default risk hints on dashboards                       | Rule-based baselines first (z-score vs property history); LLM turns flagged rows into plain-language explanations                                                                                                                               |

### Phase 2+

- Scheduled narrative reports (weekly org digest by email).
- Lease-expiry renewal suggestions with drafted retention offers.
- Maintenance photo analysis (vision input) to pre-fill triage.
- Occupant-facing FAQ assistant scoped to their own lease data.

### Guardrails

- AI output is always **suggestive**: drafts require explicit send/copy;
  triage requires staff confirmation; flags link to the underlying data.
- Every prompt includes only the minimum scope needed (org id, not raw PII,
  unless the feature requires it — cloud LLMs approved by owner decision).
- Timeouts + graceful failure: AI being down never blocks core flows.

---

## 5. Reporting engine (non-AI foundation)

AI sits **on top of** a deterministic reporting layer, never instead of it:

1. **Query services** (`App\Services\Reporting\*`) produce typed datasets:
   occupancy rate, rent collected vs billed, arrears aging, expense totals by
   category/asset, maintenance SLA times, lease-expiry pipeline.
2. These power dashboard cards (already partially built via
   `DashboardService`) **and** CSV/PDF exports.
3. The AI assistant's tools call these same services — one source of truth,
   no hallucinated numbers.

Exports: CSV first (streamed), PDF via browser print CSS (same pattern as the
lease agreement print page). Scheduled email digests arrive in Phase 2 once
notifications exist.

---

## 6. Build phases

| Phase                      | Scope                                                                                                                                                                                                                |
| -------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **R0 — Foundation**        | `ai_settings` + `ai_usage_log` migrations, AiGateway (credential resolution, logging, limits), Settings → AI page (key/provider/model/feature toggles/user allow-list), Profile → personal key, feature-hiding logic |
| **R1 — Phase-1 features**  | Ask-your-data chat (org scope, tool-based), maintenance triage, content drafting, predictive flags on dashboards                                                                                                     |
| **R2 — Reporting exports** | Reporting query services finalized, CSV export endpoints, print-to-PDF reports per property/org                                                                                                                      |
| **R3 — Advanced**          | Scheduled digests, vision-based triage, occupant FAQ assistant, renewal suggestions                                                                                                                                  |

---

## 7. Security summary

- Keys encrypted at rest; masked in UI (`sk-…abcd`); write-only form field.
- Feature-level allow-list per key; role/user allow-list per organization.
- Full usage audit trail visible to the paying owner.
- LLM receives read-only tool results — no direct DB access, no write tools.
- All destructive/financial actions remain password-confirmed human actions.
