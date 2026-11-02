---
paths:
  - tests/**
---

# Testing

## `tests/Unit` does not boot the framework

`tests/Pest.php` binds `Tests\TestCase` to `Feature` only. `Unit/Actions` is bound to the TestCase
**without** `RefreshDatabase`, which stays opt-in per file.

- An action that writes through Eloquent goes in `tests/Unit/Actions/**` and calls
  `uses(RefreshDatabase::class);` at the top of the file.
- A genuinely pure test belongs in `tests/Unit/**` outside `Actions/` — do not add
  `RefreshDatabase` to the whole `Unit` directory.
- If a test needs a route, session, or HTTP call, it is a Feature test. Put it in `tests/Feature`.

## Authorization failures are redirects, not 403

Permission denials redirect with an error toast. `assertForbidden()` asserts the wrong contract.

```php
$this->actingAs($otherTenantUser)
    ->post(route('deposit-refunds.store', $deposit))
    ->assertRedirect();

assertErrorToast();
$this->assertDatabaseCount('deposit_refunds', 0);
```

Assert the side effect as well as the redirect — it proves the refusal happened before the write.

## Run

```bash
php artisan test --filter=DepositRefund     # one slice
php artisan test                            # full suite before handing back
```
