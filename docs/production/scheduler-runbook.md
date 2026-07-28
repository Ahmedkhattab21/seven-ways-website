# Scheduler Runbook

Install one cron entry only:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Current schedule:

| Command | Frequency | Safety |
| --- | --- | --- |
| `quotations:expire` | 00:10 daily | Scoped status transition, without overlap |
| `invoices:mark-overdue` | 00:20 daily | Idempotent status update, without overlap |
| `supplier-invoices:mark-overdue` | 00:30 daily | Idempotent status update, without overlap |
| `approvals:mark-overdue` | Hourly | Pending approvals only, without overlap |
| `delegations:expire` | Hourly | Active expired delegation only, without overlap |
| `notifications:generate-operational` | 07:00 daily | Idempotency keys prevent duplicates |

Laravel uses `Africa/Cairo` as the infrastructure default. Company dates remain company-scoped.
Verify with `schedule:list`, application logs, and host cron logs. Windows Task Scheduler is a
local alternative only; Linux production uses cron. Never configure duplicate cron entries.

