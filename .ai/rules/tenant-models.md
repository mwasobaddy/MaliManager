---
paths:
  - 'app/Http/Controllers/Tenant/**, app/Models/**'
---

# Tenant Models

## Editor image uploads store one image per EditorImage row
The rich-text editor pastes/drags/uploads images to POST /editor-images (route tenant.editor-images.store, app/Http/Controllers/Tenant/EditorImageController, validated by StoreEditorImageRequest). Each upload creates an App\Models\EditorImage row (HasMedia, 'image' collection, jpeg/png/webp/gif) and returns {url: $image->getUrl()}. Files live under {org}/agreement-images via StorageLayout::agreementImagesPath(). Orphaned rows are acceptable for v1. The controller lives in the tenant org-scoped 'auth' route group (routes/tenant.php).
