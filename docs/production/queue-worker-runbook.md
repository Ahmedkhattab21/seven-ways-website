# Queue Worker Runbook

Production defaults to the database driver. The forward-only `jobs` migration and the existing
`failed_jobs` table must be present.

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600
```

Use Supervisor/systemd when available, run as the application user, auto-restart on failure,
and execute `queue:restart` after deployment. Monitor `jobs`, `failed_jobs`, log volume, retry
age, and worker memory. Inspect with `queue:failed`; retry only after root-cause review.

On shared hosting without a process manager, use a locked cron entry:

```cron
* * * * * cd /path/to/app && php artisan queue:work --stop-when-empty --sleep=3 --tries=3 --timeout=120 >> /path/to/private/logs/queue.log 2>&1
```

Notifications and future long exports may be queued. Accounting posting remains synchronous
unless a separately reviewed idempotent job is introduced. Every queued financial action must
use the existing posting/idempotency keys so retries cannot duplicate documents or journals.

