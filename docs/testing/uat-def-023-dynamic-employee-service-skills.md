# UAT-DEF-023 — Dynamic employee service skills

## Root cause

The employee form placed its skills JavaScript inside `@push('scripts')`, while the
application layout does not render `@stack('scripts')`. Consequently, neither the
add-row handler nor the `service_id` prefill state reached the browser.

The skills UI is now initialized by the existing Vite-loaded `resources/js/app.js`.
Blade supplies escaped JSON through an `application/json` element, avoiding inline
JavaScript and CSP/HTML-attribute escaping problems.

## Form state and safety

- A plain `/employees/create` request starts with an empty skills state.
- A valid `branch_id` and `service_id` create one unsaved form row with the branch
  and service selected, and with primary/active enabled.
- Invalid, inactive, cross-company, or branch-unavailable prefills are not added and
  produce an Arabic warning instead of an exception.
- Rows support add, remove, and contiguous `skills[n][field]` reindexing.
- Selected services are removed from other row options to prevent duplicates.
- Changing the branch filters both services and linked users; an unavailable selected
  service is cleared with an Arabic warning.
- Validation failures restore services, levels, checkboxes, dates, notes, and row errors.
- Backend company, branch, availability, duplicate, date, and enum validation remains
  authoritative. Employee and skills are saved in one transaction only after POST.

## Manual UAT

Checked in Chrome at:

`/employees/create?branch_id=4&service_id=26&return_url=/work-orders/2`

- Branch 4 was selected.
- One row was rendered with service 26 selected.
- Primary and active were enabled.
- Add produced a second row with `skills[1]` field names.
- Remove returned the state to one row and reindexed it as `skills[0]`.
- Service 26 was excluded from the second row options.
- No employee or skill was saved during this browser check.

## Verification

| Check | Result |
| --- | --- |
| `php artisan test --filter=EmployeeCreatePrefill` | PASS — 9 tests |
| `php artisan test --filter=EmployeeServiceSkill` | PASS — 1 test |
| `php artisan test --filter=Employee` | PASS — 25 tests |
| `php artisan test --filter=WorkOrderTechnician` | PASS — 1 test |
| `npm.cmd run build` | PASS; existing unresolved-at-build asset warnings remain |
| `php artisan view:cache --env=uat.local` | PASS |
| Targeted Pint for changed PHP files | PASS |
| `vendor/bin/pint --test` | FAIL — unrelated existing style issue in `AppointmentSchedulingService.php` |
| `git diff --check` | PASS; line-ending conversion warnings only |
| Full `php artisan test` | 410 passed, 7 failed in unrelated appointment/check-in tests: the testing database lacks the pending `default_work_order_warehouse_id` migration, plus two working-hours fixture failures |

## Result

READY — Employee service-skill rows can be added, removed, restored after validation,
and prefilled safely from the originating work order.
