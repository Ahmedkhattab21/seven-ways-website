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

Web authentication is available at `/login`; authenticated users land on `/dashboard`.
Registration and password reset are intentionally not part of the current phase.

## Architecture

- `app/Core`: shared database concerns, API response helpers, exceptions, and statuses.
- `app/Modules`: business capabilities, introduced only when a phase implements them.
- `app/Http`: delivery layer and existing backward-compatible endpoints.
- `docs`: architecture, database conventions, setup, and phase audit.

Read `docs/architecture.md` before adding a module.

## Public website

The Seven Ways public website is served from `/`, with public pages for About,
Services, and Contact. It uses a standalone Blade layout and separate Vite entries,
so its CSS and JavaScript are not loaded by the ERP.

```bash
npm install
npm run dev
php artisan serve
```

Use `npm run build` for production assets. Website source files live in:

- `resources/views/website`
- `resources/css/website`
- `resources/js/website`
- `lang/ar/website.php` and `lang/en/website.php`
- `config/website.php`
- `public/assets/website`

Edit translated copy in the language files. Edit branch addresses, phone numbers,
map embeds, social links, and asset mappings in `config/website.php`. Add images,
fonts, and videos beneath `public/assets/website` and reference them from that config.

Set `WEBSITE_CONTACT_EMAIL` in `.env` to the inbox that should receive contact-form
messages. If it is empty, the configured `MAIL_FROM_ADDRESS` is used. Laravel's normal
`MAIL_*` settings control delivery.

Run the public-site tests and production build with:

```bash
php artisan test --filter=PublicWebsiteTest
php artisan test
npm run build
php artisan route:list
```

Reference-browser screenshots and the visual audit live in `docs/website`. Regenerate
them with Playwright/Chromium at the viewport sizes listed in
`docs/website/visual-comparison.md`.

## Runtime compatibility

The project currently uses Laravel `9.52.21`. The inspected CLI runtime is PHP `8.4.21`,
which still emits third-party deprecation warnings with the locked dependencies. PHP 8.2
is recommended for this phase. Upgrading Laravel or major dependencies is intentionally
outside this patch.
