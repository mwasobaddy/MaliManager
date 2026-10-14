---
paths:
    - 'app/Http/**'
---

# Http

## Validate all write endpoints with Form Requests; destructive actions need current-password confirmation

Every write endpoint (store/update/destroy/status) must use a Form Request class in app/Http/Requests/<Area>/ — never inline $request->validate() for new code, and never bare Request on a write action. There is one platform area: everything under `platform/` (users, organizations, plans, payments, expenses, roles, impersonation, assistant) uses `app/Http/Requests/Platform/`. There are no per-entity request folders — do not recreate `Requests/Users` or `Requests/Organizations`. Request class names are singular (`ImportUserRequest`, not `ImportUsersRequest`). Roles are table rows, not enum cases: validate assignable roles against `roles.name` where `is_active` is true, and treat `PlatformRole` as the set of *protected* names (`App\Models\Role::isProtected()`), not as the set of roles. Destructive actions (delete, role/permission changes, plan changes) additionally require the current password via PasswordValidationRules::currentPasswordRules() (or the RequirePassword middleware for settings routes). Validation errors surface as inline field errors or global toasts from bootstrap/app.php — do NOT add try/catch blocks in controllers just to flash error toasts; only typed catches that translate domain exceptions or recover belong there.
