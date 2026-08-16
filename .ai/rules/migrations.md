---
paths:
  - 'database/migrations/**'
  - database/migrations/0001_01_01_000000_create_users_table.php
---

# Migrations

## Edit existing migrations instead of adding new ones
Prefer editing existing migrations over creating new ones. When a table already exists and needs a column change, modify the original migration in place (and re-run migrate:fresh or manually adjust the dev DB) rather than adding a new migration. Only add a new migration for genuinely new tables.

## Null password for OAuth-only users
users.password is nullable because OAuth-only accounts (Socialite Google login) have no password. Login flows must handle null password gracefully — users who registered via OAuth can still reset/set a password via 'Forgot password'.
