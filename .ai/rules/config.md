---
paths:
  - config/tenancy.php
  - config/purifier.php
---

# Config

## Tenancy + shared Vite build asset URLs

This app ships ONE shared Vite build in public/build served to all tenants (no per-tenant ViteBundler). Keep `asset_helper_tenancy => false` in config/tenancy.php. With it true, `@vite` emits `/tenancy/assets/build/...` URLs that hit Stancl's TenantAssetsController, which only serves storage/app/public uploads -> 404 (blank tenant pages). Additionally, AppServiceProvider sets `Vite::createAssetPathsUsing()` to return root-relative paths so assets load from the current host scheme (https on tenant subdomains) instead of an absolute APP_URL (mixed-content block). Do NOT re-enable asset_helper_tenancy or remove that resolver unless switching to per-tenant builds.

## Allow editor data-* attributes in both HTML.Allowed + custom_attributes
To let a data-* attribute (e.g. data-token on span, data-layout on img) survive LeaseAgreementTemplate::sanitize() you must register it in BOTH config/purifier.php "rich_text" HTML.Allowed string (whitelists it for output) AND the top-level "custom_attributes" list (prevents the HTMLPurifier 'Attribute ... not supported' E_USER_WARNING at definition build). Either alone is insufficient.
