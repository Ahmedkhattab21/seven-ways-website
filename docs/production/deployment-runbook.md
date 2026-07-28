# Production Deployment Runbook

## Gate

1. Approve a commit SHA/tag and a maintenance window.
2. Confirm PHP 8.2 and required extensions, MariaDB/MySQL compatibility, disk space, document
   root pointing to `public/`, cron, and queue options.
3. Back up the database and persistent storage; verify backup size and access.
4. Run CI, `production:scan-migrations`, `migrate --pretend`, and the deployment dry run.
   The scanner contains three documented historical-operation reviews; any new destructive
   `up()` finding still fails closed and requires an explicit code review.

## Preferred atomic layout

```text
releases/<timestamp>
current -> releases/<timestamp>
shared/.env
shared/storage
```

Link the approved release to shared `.env` and storage, install dependencies, build assets,
warm caches, then switch `current` atomically. Keep at least the previous release.

## Reviewed release sequence

```bash
php artisan production:validate-env
php artisan production:scan-migrations
php artisan production:check-integrity
php artisan migrate --pretend
php artisan down --retry=60
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=ProductionReferenceSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan production:verify-assets
php artisan queue:restart
php artisan up
```

Run `storage:link` only for approved public files. Private attachments must never be linked.
Smoke-test `/`, `/login`, authenticated `/dashboard`, `/health`, `/health/ready`, assets,
mail, queue, scheduler, logs, and an authorized private-file download.

## Shared hosting alternative

If SSH, Node, Supervisor, or symlinks are unavailable, build the release in CI, upload the
artifact and production `vendor/`/`public/build`, keep the application outside the web root,
and use the host control panel for cron. Use a short-lived cron queue worker as described in
the queue runbook. Do not expose the project root or upload `.env`.

GitHub deployment is manual `workflow_dispatch`, defaults to dry-run, targets the protected
`production` environment, and requires an approved ref. It must not run automatically on push.
