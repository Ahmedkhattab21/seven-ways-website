# UAT-DEF-022 — Employee create prefill 500

## Result

READY — Qualified-technician creation opens safely from the work order, preselects its branch and service, and returns after saving without a 500 error.

## Actual exception and root cause

The error reference `625ff1e0-e77d-4cb4-9c78-a231a362c519` was found in
`storage/logs/laravel-2026-07-29.log`.

The exception was:

```text
Unclosed '[' on line 271 does not match ')'
```

Laravel reported the compiled view failure at
`resources/views/employees/form.blade.php:97`.

The employee form embedded a nested PHP arrow function and array directly
inside Blade's `@json` directive. The Blade compiler parsed the nested
expression incorrectly. The data is now prepared before the script and passed
to `@json` as a simple variable.

## Safe prefill and save behavior

- `branch_id` is limited to the current company and the user's accessible branches.
- A missing branch ID is ignored safely; a forbidden existing branch returns `403`.
- `service_id` must belong to the company, be active, and be available in the selected branch.
- A missing or unavailable service is ignored safely; cross-company access returns `403`.
- The prefilled skill is active and primary, but no record is saved during `GET`.
- Employee and skill creation remain in one database transaction.
- A skill failure rolls back employee creation.
- Successful creation returns to the validated work-order URL and shows:
  `تم إنشاء الفني وربط مهارة الخدمة بنجاح.`

## Return URL protection

Only an internal relative path or an absolute URL matching the current scheme,
host, and port is accepted. Accepted absolute URLs are normalized to relative
paths. Control characters, backslashes, malformed URLs, and external hosts are
rejected, preventing open redirects.

## Files

- `app/Http/Controllers/EmployeeController.php`
- `resources/views/employees/form.blade.php`
- `tests/Feature/EmployeeCreatePrefillTest.php`
- `docs/testing/uat-def-022-employee-create-prefill-500.md`

## Automated verification

- `php artisan optimize:clear --env=uat.local`: passed.
- `php artisan route:list --name=employees`: passed; 7 employee routes found.
- `php artisan test --filter=EmployeeCreatePrefill --env=testing`: 7 passed.
- `php artisan test --filter=Employee --env=testing`: 23 passed.
- `php artisan test --filter=WorkOrderTechnician --env=testing`: 1 passed.
- Targeted Pint check for the changed PHP files: passed.
- Full `vendor/bin/pint --test`: one unrelated existing style issue in
  `app/Services/AppointmentSchedulingService.php`.
- `npm.cmd run build`: passed, with existing unresolved website asset warnings.
- `php artisan view:cache --env=uat.local`: passed.
- `git diff --check`: passed; only existing line-ending warnings were emitted.

The full suite command was executed, but the test bootstrap rejected the
environment globally with:

```text
Database tests may only run in the testing environment.
```

Result: 411 failed and 4 passed. The focused tests above pass independently;
the full-suite environment failure is outside this patch.

## Manual UAT

No employee or work order was created or changed automatically. The manual
creation of `TECH-CAI-001`, return to `/work-orders/2`, and final assignment
remain for the tester to perform through the UI as requested.
