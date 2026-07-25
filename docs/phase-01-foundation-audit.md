# Phase 01 foundation audit

Audit date: 2026-07-25.

## Stack and versions

- Laravel `9.52.21`, PHP constraint `^8.0.2`, Sanctum `3.x`.
- Runtime inspected with PHP `8.4.21`; this is newer than the project's dependency era
  and emits deprecation notices from Symfony and Termwind.
- MySQL is configured and all five existing migrations were reported as run.
- Vite `4.x`, Laravel Vite Plugin `0.7.x`, Axios `1.x`; no frontend framework.
- PHPUnit `9.5.x` and Laravel Pint `1.x`.

## Existing structure and behavior

The repository is a near-stock Laravel application. It has web/API routes, two small
controllers, the default `User` model, Blade welcome page, and no service, repository,
policy, role, company, or branch implementation.

Existing application endpoints are `/`, `/api/welcome`, and `/api/user`. Before the
limited pre-UI patch, the last route was duplicated and its effective public definition
returned three hard-coded demo users. It now returns only the authenticated user.

## Existing tables and relationships

- `users`: standalone user identity; no roles, companies, or branches.
- `password_resets`: keyed by email; no foreign key.
- `failed_jobs`: queue failure payloads.
- `personal_access_tokens`: polymorphic token owner relation used by Sanctum.
- `tasks`: title, description, priority, and timestamps; no model or relationships found.

No business-domain relationships or tenant columns exist.

## Findings by severity

### High

1. `phpunit.xml` inherits the normal MySQL connection. Future database-resetting tests
   could modify a developer or shared database unless a dedicated test DB is configured.

### Medium

1. PHP 8.4 produces extensive dependency deprecations for the Laravel 9 lockfile.
2. Existing API success formats are inconsistent and cannot be changed safely without
   versioning or a client compatibility check.
3. CORS was an unconditional wildcard; it is now environment-configurable but defaults
   to `*` to preserve local compatibility.
4. The `tasks` migration has no indexes, ownership, model, routes, or validation. Its
   purpose is unclear and it is not expanded in this phase.

### Low

1. Generated build assets and runtime storage artifacts exist locally; without Git
   metadata their tracked state could not be verified.
2. Existing controllers contain commented alternatives and non-PSR formatting.
3. The default README did not describe this application.

The source scan found no committed credential literals outside environment-backed
configuration. The actual `.env` was inspected by key name only and was not modified.

## Reusable parts

Laravel routing, middleware, Sanctum, the user identity base, migrations, Vite pipeline,
exception handler, and PHPUnit bootstrap are reusable. Existing endpoints remain in place.

## Foundation decisions

- Adopt a modular monolith: shared primitives in `app/Core`, future capabilities in
  `app/Modules/<Name>`.
- Retain bigint internal keys and use ordered UUIDs as public identifiers on future
  domain models.
- Keep author tracking, soft deletes, and tenant scoping opt-in.
- Standardize new API responses and API exception rendering without rewriting existing
  successful payloads.
- Add a database-aware `/api/health` endpoint that exposes no connection details.
- Use PHP-compatible constant classes rather than native enums because `composer.json`
  still permits PHP 8.0.

## Changes made

Added Core base-model concerns, business exceptions, response helper, shared statuses,
central API error mappings, health endpoint, modular-boundary documentation, environment
configuration for timezone/CORS/token expiry, and focused tests. No migration or business
module was created.

A limited pre-UI patch removed the duplicate public `/api/user` route. The endpoint now
exists once behind `auth:sanctum`, returns only the authenticated model's visible fields,
and has unauthenticated/authenticated feature coverage. The public health endpoint also
has failure-path coverage proving that internal exception details are not returned.

## Commands

```bash
composer install
php artisan migrate
npm install
npm run dev
php artisan test
vendor/bin/pint --test
npm run build
```

## Verification results

- `composer validate --no-interaction`: valid.
- `php artisan migrate --force`: database reachable; nothing pending.
- `php artisan test`: 10 tests passed in 0.63 seconds.
- `php artisan route:list`: 8 routes; `/api/user` appeared once.
- Targeted `vendor/bin/pint --test`: the two patch PHP files passed.
- Full `vendor/bin/pint --test`: found four pre-existing style failures in
  `app/Console/Kernel.php`, `UserController.php`, `WelcomeController.php`, and
  `RedirectIfAuthenticated.php`; they were left untouched to keep this patch limited.
- `npm run build`: 58 modules transformed; production build succeeded.
- Laravel commands and Pint emitted PHP 8.4 deprecation notices from locked third-party
  dependencies; these did not fail the checks.

## Deferred work and risks

- Select and configure a dedicated testing database before persistence tests.
- Implement companies, branches, memberships, and explicit authorization before any
  tenant-owned module.
- Decide the supported PHP deployment version or schedule a framework upgrade.
- Inventory the purpose and ownership of `tasks` before altering its schema.
- Add CI once the repository is initialized or its actual Git root is available.

The project is ready for phase 02 identity/company/branch design, but not for commercial
modules until tenant and authorization boundaries are implemented.
