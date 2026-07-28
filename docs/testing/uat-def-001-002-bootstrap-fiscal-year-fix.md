# UAT-DEF-001/002 Bootstrap and Fiscal-Year Fix

## Root cause

Fiscal years could be created through the generic Reference Data endpoint with
an `open` status. The accounting lifecycle service correctly requires complete
period coverage before opening, but the generic endpoint bypassed that workflow.
The generic fiscal-year screen also showed the same data through a second UI.

Bootstrap access additionally re-synchronised the company-owner role with
`sync()`, which could remove permissions added by later module seeders.

## Changes

- New fiscal years are forced to `draft`; request values cannot set `status` or
  `is_current` during creation.
- Generic `/settings/reference/fiscal-years` routes redirect to the accounting
  fiscal-year screen.
- Fiscal-year lifecycle remains `draft -> generate complete periods -> open`.
- Added `uat:repair-fiscal-year {code}`. It is blocked in production, verifies
  the Seven Ways company and absence of periods/financial data, repairs only an
  empty year, generates monthly periods, and opens through the official service.
  Re-running the command is idempotent after the final state is reached.
- Foundation and bootstrap role seeding now preserves existing permissions.

## FY-2026 UAT state

Before repair: `open`, zero normal periods, no journal entries.

After repair: `open`, `is_current=true`, 12 monthly periods covering
`2026-01-01` through `2026-12-31`, with no financial values converted or
historical documents changed.

Backup before UAT repair:
`D:\xxamp\uat-backups\seven_ways_uat_before_fiscal_repair_20260728_1725.sql`.

## Verification

- `FiscalYearLifecycleTest`: 3 passed.
- Full suite: 321 passed.
- `uat:repair-fiscal-year FY-2026 --env=uat.local`: READY.
- Re-run: READY, no duplicate periods.
- Owner retains accounting and treasury permissions after reference/bootstrap
  seeding.

During verification, `ProductionReferenceSeeder` was run against the isolated
UAT database to validate reference data. It created a non-production UAT
company/branch reference record; this was not performed by the repair command
and no financial documents or posted values were changed. The isolated UAT
database was not restored or altered destructively.

No production database was accessed and no `migrate:fresh` or `db:wipe` was run.
