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

| Record | Type | Value |
| --- | --- | --- |
| `malimanager.test` | `A` | Your server IPv4 |
| `*.malimanager.test` | `A` | Your server IPv4 (**required** — org subdomains) |
| `malimanager.test` | `AAAA` | Optional: server IPv6 |
| `*.malimanager.test` | `AAAA` | Optional: server IPv6 |

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

### Workers & scheduler (critical)

```bash
# Queue worker — OTP mails and any queued jobs
php artisan queue:work --tries=3

# Scheduler cron entry
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Add the queue worker under a process manager (Supervisor) so it auto-restarts.

### Server notes

- Serve the site for **both** `malimanager.test` **and** `*.malimanager.test`
  (nginx: `server_name malimanager.test *.malimanager.test;`). Laravel Forge /
  Laravel Cloud handle this when you add the wildcard domain.
- Install a wildcard SSL for the above `server_name`.
- Ensure `storage/` and `bootstrap/cache/` are writable by the web user.

---

## 8. Future / not-yet-wired items

- **Enterprise tier** (`has_dedicated_db`, `has_custom_domain`) is *not yet
  implemented*. When it is: dedicated DB provisioning + per-tenant DB migrations
  will require the `DatabaseTenancyBootstrapper` to be enabled for those tenants,
  and custom domains will need SAN certs / validation.
- **Sub-permission gating** on org roles is only partially enforced; org owners
  currently bypass all checks. Revisit before opening the app to non-owner staff.
- **Rate limiters** already exist for `login` and `otp` (`routes/web.php`); tune
  values once real traffic exists.

---

## 9. Ops checklist (before going live)

- [ ] Wildcard DNS `*.malimanager.test` + apex A record pointing at the server
- [ ] Wildcard SSL installed for `malimanager.test` and `*.malimanager.test`
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_DOMAIN=.malimanager.test`, `TENANT_SUBDOMAIN_BASE=malimanager.test`
- [ ] `.env`: real DB, Redis (cache/session/queue), SMTP mail credentials
- [ ] `GOOGLE_REDIRECT_URI` registered in the Google Cloud Console
- [ ] `PASSKEYS_USER_HANDLE_SECRET` set to a stable random value
- [ ] `domains` table rows match the production base domain (see §6)
- [ ] `php artisan migrate --force` + config/route/view/event caches built
- [ ] Queue worker running under Supervisor; cron for the scheduler
- [ ] Storage symlink created; `storage/` writable
- [ ] Log channel tailed after first deploy to catch tenancy bootstrap errors

---

*Keep this file updated whenever a new production requirement is discovered
(e.g. domain changes, new integrations, worker topology).*