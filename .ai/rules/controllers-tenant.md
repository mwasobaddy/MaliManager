---
paths:
  - 'config/tenancy.php, app/Models/**, app/Http/Controllers/Tenant/**'
---

# Controllers Tenant

## Media uses the central public disk, not per-tenant filesystem roots
FilesystemTenancyBootstrapper is DISABLED in config/tenancy.php. All medialibrary media (lease agreement PDFs, expense receipts, inspection photos, editor images) is stored on the central `public` disk (storage/app/public) and served via central getUrl() /storage/ URLs (needs `php artisan storage:link`). If the filesystem bootstrapper is re-enabled, uploads go to storage/tenant{id}/app/public which getUrl() cannot resolve -> broken images/documents. Keep it disabled; see config comment.
