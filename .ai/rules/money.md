---
paths:
  - database/migrations/**
  - app/Models/**
  - app/Data/**
---

# Money

Money is stored as `decimal(14,2)` and cast with `'amount' => 'decimal:2'`.

Do not introduce integer cents. This app already has one money representation; a second one
creates a conversion bug at every boundary between old and new code.

- Columns: `decimal(14,2)`.
- Casts: `'decimal:2'` on the model.
- In DTOs and actions, carry the same decimal string/number the column holds. Do not multiply by 100.
- Never floats in PHP arithmetic on money — use `bcmath` or the decimal cast.

If the team later decides to move to integer cents, that is a migration across the whole app, not a
per-feature choice.
