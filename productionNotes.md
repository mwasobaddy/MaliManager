# Production Notes — MaliManager

This is a living guide for what to configure on a production server when hosting
MaliManager live. Keep it updated whenever a change requires a production step.

---

## 1. The multi-tenant domain model

MaliManager runs on **subdomain-based tenancy** (stancl/tenancy, single database
for all tiers except Enterprise).

- **Central app** (auth, onboarding, admin): served on the apex domain
  `malimanager.test`.
- **Each organization** gets its own subdomain: `{slug}.malimanager.test`
  (e.g. `sunset.malimanager.test`).
- Property URLs live under the org subdomain:
  `{slug}.malimanager.test/properties/{property-slug}`.

Everything below assumes your real domain is `malimanager.test`. **Swap it for
your actual registered domain everywhere.**

---

## 2. Domain & DNS configuration

| Record               | Type   | Value                                            |
| -------------------- | ------ | ------------------------------------------------ |
| `malimanager.test`   | `A`    | Your server IPv4                                 |
| `*.malimanager.test` | `A`    | Your server IPv4 (**required** — org subdomains) |
| `malimanager.test`   | `AAAA` | Optional: server IPv6                            |
| `*.malimanager.test` | `AAAA` | Optional: server IPv6                            |

The wildcard `*.malimanager.test` record is the single most important entry.
Without it, org subdomains (`{slug}.malimanager.test`) will not resolve.

> If using a registrar that won't allow a wildcard A record, point `*.malimanager.test`
> at a DNS provider that supports it (e.g. Cloudflare, Route 53).

### Central admin subdomain (optional but recommended)

`central.malimanager.test` is already listed in `config/tenancy.php`
`central_domains`. Point an `A` record for it at the server if you want a
dedicated central URL, or keep using the apex domain.

---

## 3. TLS / SSL certificates

Because tenants live on `*.malimanager.test`, **one wildcard certificate** covers
all org subdomains plus the apex:

- `*.malimanager.test`
- `malimanager.test`

You cannot use a free single-domain cert from Let's Encrypt for tenant subdomains.
Use:

- A **wildcard certificate** obtained via DNS-01 challenge (Let's Encrypt, or
  your hosting provider's wildcard SSL), **or**
- A reverse proxy / CDN with automatic wildcard HTTPS (Cloudflare, Fly, etc).

### Why HTTPS is mandatory

- **Passkeys (WebAuthn)** in `config/fortify.php` derive `relying_party_id` from
  `APP_URL`. WebAuthn requires a secure context (HTTPS) and a stable RP ID.
- `SESSION_DOMAIN` cookies are shared across subdomains and must be `Secure`.
- Google OAuth redirects must match a registered HTTPS callback.

---

## 4. Environment variables (`.env` on the server)

Copy these into the production `.env` and adjust:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://malimanager.test

# Shared session across central + tenant subdomains (critical)
SESSION_DOMAIN=.malimanager.test

# Base used when auto-creating org subdomains in TenantService
TENANT_SUBDOMAIN_BASE=malimanager.test

# Database (single shared DB for all orgs except Enterprise)
DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=malimanager
DB_USERNAME=...
DB_PASSWORD=...

# Queue — must be a real worker-backed driver
QUEUE_CONNECTION=redis   # or 'database'

# Cache
CACHE_STORE=redis        # or 'database'

# Sessions — production should not use 'file'
SESSION_DRIVER=redis     # or 'database'

# Mail — OTP codes are sent by email
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@malimanager.test
MAIL_FROM_NAME="MaliManager"

# Google OAuth
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://malimanager.test/auth/google/callback

# Passkeys user-handle secret (generate once, keep stable)
PASSKEYS_USER_HANDLE_SECRET=...

# Filesystem — tenant documents (lease agreements, template files) go through
# the default disk. Local dev uses 'local'; production should use S3.
FILESYSTEM_DISK=s3
MEDIA_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=malimanager-production
AWS_URL=...                  # optional (CloudFront / custom endpoint)
AWS_USE_ACCELERATOR=false
```

### `SESSION_DOMAIN` — why it matters

`SESSION_DOMAIN=.malimanager.test` makes the session cookie shared across
`malimanager.test` and every `*.malimanager.test` subdomain. Without it, a user
completing onboarding on the central domain would be logged out the moment they're
redirected to their org subdomain.

> Locally (Valet/Herd) this is `.malimanager.test` too. On `localhost` it must be
> `null` — that's why the dev `.env` differs from `.env.example`.

---

## 5. Tenancy configuration (`config/tenancy.php`)

`central_domains` must include **only** domains that serve the central app:

```php
'central_domains' => [
    'malimanager.test',
    'central.malimanager.test',
],
```

Remove `localhost` / `127.0.0.1` from `central_domains` in production (or keep
them — they're harmless but should never be reachable on a live server).

`subdomain_base` already reads `TENANT_SUBDOMAIN_BASE` from `.env` — this is what
`TenantService::createOrganization()` uses to create `{slug}.malimanager.test`.

---

## 6. Data migration when changing the base domain

Org subdomains are stored **literally** in the `domains` table (e.g.
`sunset.malimanager.test`). If you change the domain (say `.test` → `.com`), you
must rewrite existing rows:

```sql
UPDATE domains
SET domain = CONCAT(SUBSTRING_INDEX(domain, '.', 1), '.malimanager.com')
WHERE domain LIKE '%.malimanager.test';
```

Do this once, right after switching `TENANT_SUBDOMAIN_BASE` and `central_domains`.

---

## 7. Deploy steps (fresh deploy)

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
```

### Seed platform financial data (optional, dev/staging)

For development or staging environments, seed sample subscription payments and
platform expenses so the Admin dashboard graphs show meaningful data:

```bash
php artisan db:seed --class=PlatformFinancialSeeder
```

This generates payments and expenses across 2024–2026. It is idempotent — it
skips if data already exists. **Do not run in production** unless you want
sample data; the seeder checks `count() > 0` and exits early.

### Tenancy note: `FilesystemTenancyBootstrapper`

The `FilesystemTenancyBootstrapper` in `config/tenancy.php` is **disabled**
(commented out). All uploaded media (lease agreements, receipts, inspection
photos, editor images) lives on the **central** `public` storage disk, not in
per-tenant paths. If you re-enable `FilesystemTenancyBootstrapper` for
Enterprise dedicated-database tenants, you must also update the media path
generator and ensure `getUrl()` emits tenant-scoped URLs.

### Workers & scheduler (critical)

```bash
# Queue worker — OTP mails and any queued jobs
php artisan queue:work --tries=3

# Scheduler cron entry
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Add the queue worker under a process manager (Supervisor) so it auto-restarts.

The scheduler runs **`activity:prune --days=180` daily** — the audit log
(`activity_log`) grows unbounded otherwise and the audit pages query it. Verify
the first scheduled run happened after go-live (`schedule:interrupted`/log tail).

### Tenant document storage layout (S3)

All tenant documents are written through `Storage::disk(config('filesystems.default'))`
via `App\Support\StorageLayout` + medialibrary's `TenantPathGenerator`:

```
{organization-slug}/templates/lease/               agreement template documents
{organization-slug}/{property-slug}/lease/          uploaded lease agreements (per media id)
{organization-slug}/{property-slug}/inspections/    unit inspection photos
{organization-slug}/{property-slug}/maintenance/    maintenance request photos
{organization-slug}/{property-slug}/expenses/       expense receipts
{organization-slug}/land-parcels/{parcel}/expenses/ land-parcel expense receipts
```

- Folders are keyed by **slug at creation time** and are never moved on rename.
  Renaming an organization or property leaves existing files in place.
- `.keep` marker files create the skeleton when an organization/property is
  created; they can be ignored by lifecycle rules but don't delete the folders.
- On S3, set an S3 **lifecycle policy only if you intend it** — nothing in the
  app expires these files.
- Existing media created before this layout (e.g. land-parcel photos under the
  default `{media-id}/` paths) stays where it is; only new uploads use tenant
  paths. Do not run ad-hoc S3 syncs/moves without updating `media` rows.

### Server notes

- Serve the site for **both** `malimanager.test` **and** `*.malimanager.test`
  (nginx: `server_name malimanager.test *.malimanager.test;`). Laravel Forge /
  Laravel Cloud handle this when you add the wildcard domain.
- Install a wildcard SSL for the above `server_name`.
- Ensure `storage/` and `bootstrap/cache/` are writable by the web user.

---

## 7b. AI (bring-your-own-key)

AI features run on **user/org-supplied API keys** — the platform holds none and
pays nothing for tokens.

- Keys live in the `ai_settings` table, **encrypted at rest**; masked after save.
  Resolution order per user: personal key (Settings → AI) → organization key
  (Organization → AI settings) → features hidden if neither exists.
- Organization keys are gated by feature toggles + a member/role allow-list
  chosen by the owner. A `monthly_token_limit` soft cap (0 = unlimited) is
  enforced in `App\Support\Ai\AiGateway::withinMonthlyLimit()`.
- Every dispatch writes an `ai_usage_logs` row. Owners see per-user totals for
  the current month on the org AI settings page; users see their own on their
  profile page.
- The weekly digest (`ai:send-digest`, Mondays 08:00) includes an AI narrative
  only when the organization has a key; otherwise it degrades to numbers-only.
- **NVIDIA caveat**: NIM keys only work for models the account has _activated_.
  After generating a key at build.nvidia.com, open each desired model page and
  run one request ("Try it") to unlock it — otherwise calls return
  `404 Function not found for account` even though `/v1/models` lists them.
  Models also get **retired** over time (e.g. deepseek-r1, llama-3.3-70b were
  removed from the hosted catalog): if a configured model starts returning
  404 for every account, pick a replacement from the current
  `/v1/models` listing.

No action is needed at deploy time beyond ensuring the scheduler runs (digest)
and that outbound HTTPS to the configured provider endpoint is permitted
(OpenAI, Anthropic, DeepSeek, OpenRouter, NVIDIA NIM, Gemini, Groq, Mistral,
Ollama, Perplexity, xAI, Z.ai — whichever keys your orgs configure).

---

## 7c. Global search endpoint (ops / security note)

`GET /search` is an **authenticated, JSON** endpoint powering the top-nav command
palette. It introduces no server configuration, but a few things are worth knowing
in production:

- **Cross-org reach**: on a tenant subdomain it returns only the active org's
  records; on the **central domain**, platform admins can search **across every
  organization**. A compromised central admin account therefore exposes org-wide
  data through search — keep central admin access tightly scoped.
- **PII gating**: the `users` type (name/email/phone) is only returned to searchers
  holding the `manageUsers` ability. Audit/search logs can still reveal broad
  enumeration; add rate limiting on `/search` once real traffic exists.
- **Global suspension middleware**: `EnsureUserIsActive` runs on every request and
  logs out + redirects any authenticated user whose `status` is not `active` to the
  `/suspended` page. It's harmless at steady state, but a bug here would log every
  user out — watch for a spike of `/suspended` requests in access logs after deploy.
- **Build dependency**: the frontend build runs `laravel/wayfinder`, which reflects
  every route-referenced controller. Any production build must have all controller
  classes present and `routes/platform.php` loaded (it is `require`d from
  `web.php`). Dangling controller imports fail `npm run build`, not just runtime.

---

## 8. Future / not-yet-wired items

- **Enterprise tier** (`has_dedicated_db`, `has_custom_domain`) is _not yet
  implemented_. When it is: dedicated DB provisioning + per-tenant DB migrations
  will require the `DatabaseTenancyBootstrapper` to be enabled for those tenants,
  and custom domains will need SAN certs / validation.
- **Rate limiters** already exist for `login` and `otp` (`routes/web.php`); tune
  values once real traffic exists.
- **Spatie permission cache** uses the default cache store. With `CACHE_STORE=redis`
  this is fine; if you ever switch the cache driver, run
  `php artisan cache:clear` after deploy so role/permission lookups don't serve
  a stale set (a stale empty set surfaces as
  `PermissionDoesNotExist` during seeding/grants).

---

## 9. Ops checklist (before going live)

- [ ] Wildcard DNS `*.malimanager.test` + apex A record pointing at the server
- [ ] Wildcard SSL installed for `malimanager.test` and `*.malimanager.test`
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_DOMAIN=.malimanager.test`, `TENANT_SUBDOMAIN_BASE=malimanager.test`
- [ ] `.env`: real DB, Redis (cache/session/queue), SMTP mail credentials
- [ ] `GOOGLE_REDIRECT_URI` registered in the Google Cloud Console
- [ ] `PASSKEYS_USER_HANDLE_SECRET` set to a stable random value
- [ ] `FILESYSTEM_DISK=s3` + AWS bucket credentials set (tenant documents live there)
- [ ] S3 bucket reachable: create an organization + property and confirm
      `{org}/templates/lease/.keep` and `{org}/{property}/lease/.keep` appear
- [ ] First scheduled run executed (`activity:prune` daily)
- [ ] `domains` table rows match the production base domain (see §6)
- [ ] `php artisan migrate --force` + config/route/view/event caches built
- [ ] Queue worker running under Supervisor; cron for the scheduler
- [ ] Storage symlink created; `storage/` writable
- [ ] Log channel tailed after first deploy to catch tenancy bootstrap errors
- [ ] Permissions: `RolesAndPermissionsSeeder` auto-grants all `PlatformPermissionKey` cases (including `subscription.*` and `platform-expense.*`) to the admin role; run `php artisan db:seed --class=RolesAndPermissionsSeeder` if permissions are missing
- [ ] Optional: verify first `ai:send-digest` Monday run — orgs without keys still get numbers-only digests
- [ ] Platform subscription payments + expenses: verify `subscription_payments` and `platform_expenses` migrations ran; seed with `PlatformFinancialSeeder` if desired
- [ ] Dashboard: verify Admin tab shows financial graphs with year/month filters; confirm `available_years` is populated from seeded data

---

_Keep this file updated whenever a new production requirement is discovered
(e.g. domain changes, new integrations, worker topology)._
