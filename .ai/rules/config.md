---
paths:
  - config/tenancy.php
---

# Config

## Tenancy + shared Vite build asset URLs
This app ships ONE shared Vite build in public/build served to all tenants (no per-tenant ViteBundler). Keep `asset_helper_tenancy => false` in config/tenancy.php. With it true, `@vite` emits `/tenancy/assets/build/...` URLs that hit Stancl's TenantAssetsController, which only serves storage/app/public uploads -> 404 (blank tenant pages). Additionally, AppServiceProvider sets `Vite::createAssetPathsUsing()` to return root-relative paths so assets load from the current host scheme (https on tenant subdomains) instead of an absolute APP_URL (mixed-content block). Do NOT re-enable asset_helper_tenancy or remove that resolver unless switching to per-tenant builds.
