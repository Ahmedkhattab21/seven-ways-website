# Seven Ways ERP

Foundation for a modular ERP serving Seven Ways vehicle-protection operations.
Phase 01 contains shared architecture only; business modules are intentionally deferred.

## Requirements

- PHP 8.2 recommended for the current Laravel 9 dependency set
- Composer 2
- MySQL 5.7+ or MariaDB 10.3+
- Node.js 16+ and npm

## Quick start

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Create an empty database, set the `DB_*` values in `.env`, then run:

```bash
php artisan migrate
npm install
npm run dev
php artisan serve
```

Never commit `.env`. In production set `APP_ENV=production`, `APP_DEBUG=false`,
an explicit `CORS_ALLOWED_ORIGINS`, and HTTPS-aware proxy settings.

## Checks

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

`GET /api/health` verifies the application and database without returning connection
details. See `docs/development-setup.md` for environment and test-database guidance.

## Architecture

- `app/Core`: shared database concerns, API response helpers, exceptions, and statuses.
- `app/Modules`: business capabilities, introduced only when a phase implements them.
- `app/Http`: delivery layer and existing backward-compatible endpoints.
- `docs`: architecture, database conventions, setup, and phase audit.

Read `docs/architecture.md` before adding a module.

## Runtime compatibility

The project currently uses Laravel `9.52.21`. The inspected CLI runtime is PHP `8.4.21`,
which still emits third-party deprecation warnings with the locked dependencies. PHP 8.2
is recommended for this phase. Upgrading Laravel or major dependencies is intentionally
outside this patch.
