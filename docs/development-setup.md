# Development setup

## Environment

Copy `.env.example` to `.env`, generate `APP_KEY`, and set local database values. Keep
real credentials only in `.env` or the deployment secret store.

Important production values:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=UTC
CORS_ALLOWED_ORIGINS=https://erp.example.com
SANCTUM_EXPIRATION=120
```

Use a comma-separated allowlist for multiple CORS origins. Configure trusted proxies at
the hosting boundary only when the reverse-proxy addresses are known.

## Install and run

```bash
composer install
php artisan key:generate
php artisan migrate
npm install
npm run dev
php artisan serve
```

Build production assets with `npm run build`.

## Tests and quality

The current test configuration inherits the `.env` database. Before tests can create or
mutate data, configure a dedicated test database in `.env.testing` or enable SQLite in
`phpunit.xml`. Never point tests using `RefreshDatabase` at a shared or production DB.

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

Use a PHP version supported by both `composer.json` and locked dependencies. PHP 8.4
currently emits dependency deprecation notices with this Laravel 9 lockfile.

## Adding a module

1. Confirm the capability and tenant boundary.
2. Create only the module folders needed by working code.
3. Add a forward migration with constraints and indexes.
4. Add policy/authorization before exposing routes.
5. Use Form Requests and API Resources for public input/output.
6. Put multi-write workflows in a transaction.
7. Add focused feature tests, including cross-tenant denial.
8. Document state transitions and API compatibility.
