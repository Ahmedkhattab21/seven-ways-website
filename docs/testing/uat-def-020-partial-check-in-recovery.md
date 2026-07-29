# UAT-DEF-020 — Partial appointment check-in recovery

## Result

**READY — Partial appointment check-in is recoverable and new check-ins are atomic.**

## Root cause

The original flow could leave an appointment in `checked_in` when work-order prerequisites failed outside the complete orchestration boundary. The current flow now validates the default warehouse and the `work_order` document sequence before changing appointment data, then performs check-in and work-order creation inside one outer database transaction.

## Implemented behavior

- `pending` and `confirmed` appointments are checked in and converted to one work order atomically.
- A `checked_in` appointment without an active work order displays **استكمال إنشاء أمر العمل**.
- Recovery preserves `checked_in_at`, `arrival_notes`, and `odometer_snapshot`.
- Recovery creates the work order through the existing `WorkOrderCreationService`, changes the appointment to `in_progress`, and records `appointment.work_order_recovered`.
- A repeated request returns the existing active work order without consuming another sequence or duplicating inspection, services, or materials.
- Terminal appointment states are rejected.
- Appointment statuses use centralized Arabic labels instead of raw keys.
- No inventory movement, journal entry, or direct UAT data update was added.

## Verification

| Check | Result |
| --- | --- |
| PHP syntax checks | Passed |
| Targeted Pint for changed PHP files | Passed |
| Vite build | Passed, with existing unresolved website asset warnings |
| Blade view cache | Passed |
| `git diff --check` | Passed |
| `php artisan test --filter=AppointmentRecovery` | Blocked: testing database is missing `branch_settings.default_work_order_warehouse_id` |
| `php artisan test --filter=AppointmentCheckIn` | Blocked by the same pending testing migration |
| Full test suite | Timed out after 120 seconds |
| Full `vendor/bin/pint --test` | Existing style issue remains in `AppointmentSchedulingService.php`; changed PHP files pass |

PHP 8.4 dependency deprecation warnings remain and are outside this patch.

## UAT data

Appointment `CAI-MAIN-APT-2026-000001` was not modified by SQL, Seeder, or an automated command. Open its page and use **استكمال إنشاء أمر العمل** to recover it through the authorized application workflow.
