# Phase 17 — Employees, Commissions, Expenses and Advances

## Scope

Implemented on the isolated MariaDB environment only. No historical database files or financial documents were changed.

## Database

Forward-only migrations add:

- Commission rules, accruals, settlements, and settlement lines.
- Expense categories, claims, and claim items.
- Employee advances/custody and settlement sources.
- Restrictive foreign keys, MariaDB-safe names, source/idempotency uniqueness, currency/company/branch dimensions, and decimal amounts.
- No stored employee balance and no destructive `down()` operation.

## Files

- Migrations: `2026_07_27_100000_create_employee_finance_tables.php`,
  `2026_07_27_110000_add_employee_finance_line_uuids.php`.
- Models: the nine `EmployeeCommission*`, `EmployeeExpense*`, and `EmployeeAdvance*` models.
- Factories: one factory for every new model.
- Services: `EmployeeFinanceService.php`; employee-finance builders in
  `AccountingPostingService.php`; reversal module support in `JournalEntryService.php`.
- HTTP: `EmployeeFinanceController.php`, `EmployeeFinanceReportController.php`,
  six employee-finance requests, private attachment handling/policy, and `routes/web.php`.
- UI: `resources/views/employee-finance/index.blade.php`,
  `resources/views/employee-finance/report.blade.php`, and the permission-scoped sidebar link.
- Data: `EmployeeFinanceSeeder.php` and its idempotent registration in `DatabaseSeeder.php`.
- Tests: `PhaseSeventeenEmployeeFinanceTest.php`.

## Business Rules

- Commission rules support net sales, margin snapshot, fixed, employee/branch/product/service scope, priority, effective dates, overlap prevention, min/max, and dynamic currency.
- Calculations are server-side and idempotent. VAT is excluded from net-sales basis.
- Issued credit notes create immutable, idempotent negative adjustments linked to the original accrual.
- Open settlements reserve accrual amounts and prevent duplicate or excess allocation.
- Accrual posting: debit commission expense, credit employee payable; negative adjustments reverse the sides.
- Expenses calculate net, configured tax, and total on the server. Posting debits expense/input VAT and credits the selected employee payable/advance account.
- Payments reuse posted Treasury cash documents and must match employee, branch, currency, amount, and offset account.
- Advances and custody reuse Treasury payment/receipt documents, support partial settlement, reject over-settlement, and cannot close early.
- Company, accessible branch, employee ownership, accounting period/module locks, approval limits, and Separation of Duties are enforced in backend services.
- Attachments remain private, allowlisted, size-limited, draft-only, and branch-authorized.

## Access and UI

- Idempotent permissions and finite role approval limits are seeded.
- Accountants can create/submit/review/post/reverse but cannot approve.
- Managers/owners approve; creators cannot approve their own records.
- RTL pages cover rules, accruals, settlements, expense claims, advances/custody, state-aware actions, and filtered reports.
- Reports derive outstanding amounts from accrual/settlement and ledger sources; no stored balances.

## Seed Safety

The seeder adds permissions, safe role mappings, reference expense categories, document sequences, approval limits, and accounting mappings. It creates no commission, claim, advance, journal, payment, or stored balance and is idempotent.

## Tests

`PhaseSeventeenEmployeeFinanceTest` covers schema/seed safety, idempotency, net-sales VAT exclusion, overlap prevention, negative credit-note adjustment, settlement reservation/over-allocation, SOD, one-time posting, server totals/tax, cross-company scope, and early-close prevention.

## Verification Results

| Command | Result |
| --- | --- |
| `php artisan migrate --pretend` | Passed on isolated Phase 17 database |
| `php artisan migrate --force` | Passed; both Phase 17 migrations ran |
| `php artisan db:seed --force` | Passed; repeated Employee Finance seed passed |
| `php artisan test --filter=PhaseSeventeen` | 8 passed |
| `php artisan test --filter=EgyptLocalization` | 9 passed |
| `php artisan test --filter=PhaseFifteen` | 31 passed |
| `php artisan test` | 249 passed in 1059.35s |
| `vendor/bin/pint --test` | 1,313 files passed |
| `composer validate` | Valid |
| `npm.cmd run build` | Passed; existing runtime asset-resolution warnings remain |
| `php artisan view:cache` | Passed |
| `php artisan route:list` | Passed; 497 routes |
| `git diff --check` | Passed; only Git CRLF conversion notices |

The read-only Egypt audit reports `EG`, `EGP`, zero posted history, zero SAR documents/journals, and zero VAT 15 lines on the isolated clean database.

## Decisions and Remaining Risk

- Commission expense is recognized when an approved accrual is posted.
- Expense claims may credit the employee payable or the matching advance receivable account.
- Cash/bank settlement is never duplicated; Phase 17 links to the existing Treasury engine.
- Historical database recovery is still a separate NO-GO gate.
