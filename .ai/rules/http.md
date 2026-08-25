---
paths:
  - 'app/Http/**'
---

# Http

## Validate all write endpoints with Form Requests; destructive actions need current-password confirmation
Every write endpoint (store/update/destroy/status) must use a Form Request class in app/Http/Requests/<Area>/ — never inline $request->validate() for new code, and never bare Request on a write action. Destructive actions (delete, role/permission changes, plan changes) additionally require the current password via PasswordValidationRules::currentPasswordRules() (or the RequirePassword middleware for settings routes). Validation errors surface as inline field errors or global toasts from bootstrap/app.php — do NOT add try/catch blocks in controllers just to flash error toasts; only typed catches that translate domain exceptions or recover belong there.
