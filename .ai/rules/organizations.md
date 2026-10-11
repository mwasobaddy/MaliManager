---
paths:
  - resources/js/pages/organizations/index.tsx
---

# Organizations

## Organizations table: row-click detail, status toggle, and URL overlays
Organizations table specifics: rows are the detail trigger (DataTable onRowClick opens the ?organization= sheet; there is no View button). The action cell is wrapped in onClick stopPropagation and the status toggle stops it too, so clicking a control never also opens the sheet. The status glyph in the Status column toggles suspend/reactivate via router.patch(orgRoutes.status(id), { status }) — NOT an Inertia <Form>, because <Form> with no bound input submits an empty body and the server receives no `status` (it returns 303 and looks like it worked). Sheet/export/import/filters overlays are driven by ?organization=&export=1&import=1&filters=1 and all read usePage().url. Export downloads through a hidden GET form (downloadExportUrl) so the session cookie is sent — never build a blob via fetch.
