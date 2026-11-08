# Disabling Passkeys

**Stack:** Laravel 13 with Laravel Fortify (v1.38) and the laravel/passkeys package (v0.2.1), on an Inertia React frontend.

This is the procedure used in this codebase to switch passkeys off without deleting anything. Every change is a comment, so restoring the feature means uncommenting.

## The key point

There are two independent switches, and the obvious one is not enough.

The Fortify feature entry controls what the application reports, so the settings screen hides the passkey section. But the passkeys package registers its own HTTP routes regardless of that flag. Disable only the feature and the login and registration endpoints stay reachable.

Do both: remove the feature entry, and stop the package registering its routes.

## Steps, in order

1. **Fortify config** — comment out the passkeys feature entry in the Fortify config file. This is what makes the application treat passkeys as unavailable.
2. **Route opt-out** — in the application service provider's registration step, call the passkeys package's route-ignoring hook. It must run during registration, before any provider boots, or the routes will already exist. This is the one line that has to execute; it cannot be commented out.
3. **Well-known endpoint** — comment out the passkey-endpoints route in the settings routes file. It advertises enroll and manage URLs to browsers and password managers.
4. **Rate limiter** — comment out the passkeys limiter in the Fortify service provider. It has no endpoint left to protect.
5. **Login page** — comment out the passkey component and the import that brings it in.
6. **Confirm-password page** — comment out the passkey option, its component import, and the generated route helpers it used.
7. **User menu** — comment out the Passkeys menu item and its now-unused icon import.
8. **Security settings page** — comment out the passkey management section, its type import, its part of the page props type, and the component itself.
9. **Passkey management component** — wrap the whole file in a block comment. The reason is below.

## Why the management component goes too

The package's routes are the source for Wayfinder's generated frontend route helpers. Once the routes are ignored, those modules are no longer generated, and any component importing them fails to type-check. The passkey management component is the only one that did. The login and confirm components use the package's client-side hooks instead, so they only needed their own imports removed.

## Verification

- The route listing shows no passkey routes.
- The well-known passkey endpoint is gone.
- The login, confirm-password and security pages show no passkey controls.
- The full test suite passes, including a test asserting the routes are absent.

## Test gotcha

A test asserting the feature was enabled passed only because the config entry was what enabled it. Calling the feature helper sets its options; it does not add the feature to the enabled list. If passkeys are re-enabled later, enable them explicitly in tests rather than relying on that helper.

## Disclaimer

This describes this application as it stands today. It is not a universal recipe.

- It assumes the default Laravel and Fortify scaffolding, with the passkeys package installed as a Fortify dependency. That dependency is why the package cannot simply be uninstalled.
- If the Laravel, Fortify or passkeys versions differ, the config entry, the route hook and the generated helper paths may differ or may not exist at all.
- If the auth pages, settings pages, layouts or user menu have been customised, the UI steps will not match. Find every place the passkey components are rendered rather than following the file list blindly.
- If another authentication path exists — custom controllers, Livewire, or a different starter kit — the route and UI surface changes accordingly.

Check the installed versions and search the codebase for passkey references before applying any of this.
