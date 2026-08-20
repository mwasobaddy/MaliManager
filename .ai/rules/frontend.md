# Frontend Rules

## Wayfinder

- Always regenerate with `php artisan wayfinder:generate --with-form`. The CLI defaults to omitting `.form` variants, which breaks `useForm`/`<Form>` pages with `TS2339: Property 'form' does not exist`. The vite config's `formVariants: true` only applies to dev-time generation, not the CLI.
- Route functions with URL params expose `.form` on the function object, not the call result: use `update.form(staff.id)` / `destroy.form(staff.id)`, never `update(staff.id).form()`.