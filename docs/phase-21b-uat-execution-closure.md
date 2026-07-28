# Phase 21B - UAT Execution Closure

Date: 2026-07-28

Scope: isolated technical UAT only; no production connection or deployment.

## Environment recovery

MariaDB 10.4.32 is running as the isolated instance on `127.0.0.1:3307`, using
`D:\xxamp\mysql\bin\my-seven-ways-testing.ini` and
`D:\xxamp\mysql\data-seven-ways-testing`. The historical instance on port
`3306` was not queried or modified. The active process accepts SQL connections.

The UAT database was backed up before seeding to this external, non-Git path:
`D:\xxamp\uat-backups\seven_ways_uat_phase21b_20260728_182118.sql` (1,423,566
bytes). No credentials or production data were used.

## UAT database and seeders

- `uat:validate-target --env=uat.local`: **Passed** (host, port, database, PHP
  and MariaDB matched the guard).
- `migrate:status --env=uat.local`: **Passed**, all migrations ran.
- `ProductionReferenceSeeder`: **Passed**.
- `SevenWaysUatSeeder`: **Passed twice**, idempotently.
- `UatPerformanceSeeder`: **Passed twice** after the base backup.
- The base UAT seeder created no posted journals, invoices, treasury
  operations, active cash sessions, cheques, or stored stock balances.

## Automated E2E and regression

| Suite | Result |
| --- | --- |
| `PhaseTwentyOne` | 14 passed, 0 failed |
| Phase 20/19/18/17/15 filters | 31/19/12/8/31 passed, 0 failed |
| EgyptLocalization | 9 passed, 0 failed |
| TreasuryManualQa | 6 passed, 0 failed |
| PublicWebsiteTest | 21 passed, 0 failed |
| Full `php artisan test` | **315 passed, 0 failed** (130.12s) |

## HTTP smoke

The UAT server ran locally on `127.0.0.1:8086`/`8087` and was stopped after
testing. `/`, `/login`, `/health`, and `/health/ready` returned 200. Protected
paths redirected guests to `/login` as expected. `/health` returned only
`{"status":"ok"}` and readiness returned safe status labels for database,
cache, storage, and queue. No secret or stack trace was exposed. Security
headers and correlation IDs were present. Browser-based manual UAT was not
claimed.

## Accounting and restore

The UAT base contained zero journals and zero stock balances. A logical restore
into `seven_ways_uat_restore` matched the source at 197 tables, 1 company, 14
users, 3 branches, 0 journals, and 0 stock balances. `production:check-integrity`
on the restored database reported zero unbalanced journals, orphan links/lines,
stock formula errors, invalid reservations, and blocking closing exceptions.
The disposable restore database was removed only after comparison; UAT was
retained.

## Queue, scheduler, and performance

`queue:work --stop-when-empty --tries=3 --timeout=120` completed successfully.
`approvals:mark-overdue`, `delegations:expire`, and
`notifications:generate-operational` were each run twice. `schedule:list` showed
the expected six tasks. The guarded performance dataset was seeded twice after
backup. No production SLA is claimed; authenticated browser timing evidence
remains pending because the scripted HTTP login session did not establish an
authenticated browser session.

## Quality commands

`production:scan-migrations`, `production:check-integrity`, and
`production:verify-assets` passed (21 assets). `config:cache`, `route:cache`,
and `view:cache` passed and were cleared afterward. `production:validate-env`
failed as expected on the non-production UAT environment because its HTTPS and
secure-cookie gates are production-only.

## Defects, manual status, and decision

No application defect was reproduced. Manual Browser UAT, Business Owner
sign-off, hosting/TLS/proxy/cron/worker/storage monitoring checks, and
authenticated performance evidence remain **Pending manual execution**. No
screenshot or sign-off is represented as passed.

```text
CONDITIONAL GO - Technical release candidate verified on isolated UAT;
manual browser/business-owner and hosting sign-off remain pending.
```

This does not authorize deployment or production credentials. The historical
Phase 21 report remains NO-GO until pending business and hosting evidence is
completed.
