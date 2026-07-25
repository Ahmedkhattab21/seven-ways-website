# Phase 10 — Work Order Execution

## Scope delivered

- Work orders created from a checked-in appointment, accepted quotation, or authorized direct entry.
- One active work order per appointment, immutable commercial snapshots, tenant/branch/warehouse checks.
- Check-in vehicle inspection, immutable completion, customer acknowledgement, and private inspection photos.
- Service technicians, append-only time logs, start/pause/resume/complete/reopen state rules.
- Quantity reservations without stock deduction; actual issue/usage/return movements with backend cost snapshots.
- Existing roll and scrap consumption services reused; no duplicate stock deduction path was introduced.
- Waste records, actual material/waste/labor totals, actual margin, and append-only status timeline.
- Final execution transition is `awaiting_quality`; quality approval, delivery, warranty, invoices, and accounting were not implemented.

## Data and security

- Migration: `2026_07_25_170000_create_work_order_execution_tables.php`.
- Tables: `work_orders`, `work_order_services`, technicians, time logs, inspections/items, materials, waste, and status logs.
- Actual cost/status/user ownership fields are backend-owned.
- Work-order deletion, status-log deletion/update, waste deletion/update, and time-log deletion are blocked.
- Policies enforce company/branch scope, technician assignment scope, private photos, and separate cost visibility.
- Transit/system warehouses cannot be selected for normal work-order issue.

## Permissions and sequences

`WorkOrderSeeder` idempotently creates the requested work-order, inspection, and material permissions and assigns restricted role sets. It creates yearly branch sequences:

- `{BRANCH}-WO-{YYYY}-`
- `{BRANCH}-VI-{YYYY}-`
- `{BRANCH}-WW-{YYYY}-`

No fake production work orders are seeded.

## Events

Events are dispatched with `DB::afterCommit`: creation, inspection completion, ready-to-start, start/pause/resume, service completion, awaiting quality, cancellation, material issue/return, roll or scrap consumption, and waste recording.

## Tests

Added `PhaseTenWorkOrderExecutionTest` with 8 focused scenarios:

1. Checked-in appointment creates exactly one active order with snapshots and inspection.
2. Start is blocked before inspection; completed inspection is immutable.
3. Duplicate open technician time logs are blocked.
4. Reservation does not deduct on-hand; issue deducts exactly once and captures cost.
5. Actual product use/waste costs are rebuilt by the backend.
6. Service completion closes logs and stops at `awaiting_quality`.
7. Cancellation releases reservations and is blocked after quality handoff.
8. Seeder idempotency plus unprivileged/cross-company access denial.

Phase 9 assertions were updated from “table does not exist” to “appointment/quotation operations create zero work orders”, preserving the original boundary after Phase 10 introduced the table.

## Verification results

- `php artisan migrate --force`: passed; Phase 10 migration applied to development.
- `php artisan db:seed --force`: passed; all seeders including `WorkOrderSeeder`.
- Testing database Phase 10 migration: passed.
- `php artisan test`: passed, **101 tests** in 86.28s.
- `vendor/bin/pint --test`: passed, **441 files**.
- `composer validate`: passed (`composer.json is valid`).
- `npm.cmd run build`: passed, Vite 4.5.14, 58 modules.
- `php artisan route:list`: passed, **191 routes** after adding the material-use route.
- `php artisan view:cache`: passed after clearing stale compiled views.
- `git diff --check`: passed.

## Remaining warnings

- Runtime is PHP **8.4.21** with Laravel **9.52.21**.
- PHP 8.4 emits vendor deprecation warnings from Symfony, Termwind, Collision, and Pint dependencies. They do not fail tests or checks.
- PHP 8.2 remains the recommended runtime for this Laravel 9 project.
- Laravel/dependency upgrades are outside this patch.
- Git reports existing LF-to-CRLF normalization notices on some tracked files; no whitespace errors were reported.

## Files

- New migration, 9 execution models, 13 events, 13 services, 4 controllers, 11 requests, 5 policies.
- New work-order list/create/detail/inspection Blade views.
- New permission/sequence seeder and 5 factories.
- New Phase 10 feature test and this report.
- Updated inventory reservation whitelist, policy registration, routes, sidebar, attachment authorization, database seeder, and Phase 9 boundary assertions.
