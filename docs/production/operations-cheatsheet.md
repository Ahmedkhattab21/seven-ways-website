# Production Operations Cheat Sheet

```bash
php artisan production:validate-env
php artisan production:scan-migrations
php artisan production:check-integrity
php artisan production:verify-assets
php artisan migrate:status
php artisan schedule:list
php artisan queue:work --sleep=3 --tries=3 --timeout=120
php artisan queue:restart
php artisan queue:failed
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan down --retry=60
php artisan up
```

Use `/health` for liveness and `/health/ready` for DB/cache/storage/queue readiness. Never paste
`.env`, passwords, tokens, account numbers, cheque numbers, or private paths into tickets/logs.
Do not retry failed financial jobs until idempotency and the original database state are checked.
Never run `migrate:fresh`, `db:wipe`, automatic `migrate:rollback`, or QA seeders.
