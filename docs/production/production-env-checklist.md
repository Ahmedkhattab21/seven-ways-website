# Production Environment Checklist

Use PHP 8.2, keep `.env` outside Git, set mode `600` where supported, and never print it
in deployment logs. Company country, currency, tax, and timezone stored in the database remain
the operational source of truth.

## Required

| Variable | Requirement |
| --- | --- |
| `APP_NAME` | Public application name |
| `APP_ENV` | `production` |
| `APP_KEY` | Generate once with `key:generate`; back it up and never rotate during deployment |
| `APP_DEBUG` | `false` |
| `APP_URL` | Canonical HTTPS URL |
| `APP_TIMEZONE` | Infrastructure default `Africa/Cairo` |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `ar` / `en` |
| `DB_*` | Production-only host, port, database, least-privilege user, and secret password |
| `LOG_CHANNEL` / `LOG_LEVEL` | `stack` and normally `warning` |
| `CACHE_DRIVER` | `file`, `database`, or a hosting-supported shared store |
| `SESSION_DRIVER` | `file` for one server; shared store for multiple servers |
| `QUEUE_CONNECTION` | `database` unless a managed queue is approved |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_HTTP_ONLY` | `true` |
| `SESSION_SAME_SITE` | `lax` or `strict` |
| `FILESYSTEM_DISK` | `local`; sensitive uploads stay under `storage/app/private` |
| `MAIL_*` | Approved SMTP endpoint and sender; credentials remain secret |
| `FORCE_HTTPS` | `true` after proxy settings are verified |
| `CORS_ALLOWED_ORIGINS` | Explicit HTTPS origins; never `*` in production |

## Hosting-dependent

- `TRUSTED_PROXIES`: comma-separated proxy IP/CIDR values supplied by the host. Never use
  `*` without a documented network boundary.
- `ASSET_URL`: only when a CDN or subdirectory deployment requires it.
- `LOG_STACK_CHANNELS`: defaults to `daily`; `stderr` may be added for managed containers.
- `LOG_DAILY_DAYS`: defaults to 14.
- Redis, S3, Pusher, and webhook variables are optional and must remain blank when unused.

Run `php artisan production:validate-env` before every production migration. It reports
missing variable names only and never prints values. After changing `.env`, run
`php artisan optimize:clear` then rebuild config, route, and view caches.

