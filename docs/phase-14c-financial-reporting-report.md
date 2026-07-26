# Phase 14C — General Ledger and Financial Reporting

## Scope delivered

- General Ledger with posted-only opening, movement, closing, and SQL running balance.
- General Journal inquiry with accounting and operational dimensions.
- Trial Balance with posting-account totals, optional descendant Header aggregation, Group/Type summaries, zero filtering, and adjusted/unadjusted foundation.
- Income Statement with revenue, cost of sales, gross profit, operating expenses, net profit, and margins.
- Balance Sheet with current-period profit as a presentation value and no closing entry.
- Direct-method Cash Flow foundation with counter-account mappings, operating/investing/financing categories, Unclassified fallback, and cash reconciliation.
- Branch and Cost Center financial views, including missing required Cost Center exceptions.
- Customer, supplier, inventory, VAT output, and VAT input control-account reconciliation without corrective posting.
- Unposted accounting source inquiry.
- Current/previous period and previous-year comparative calculations with safe zero-base percentages.
- CSV formula-injection protection, print templates, reporting permissions, policies, definition/mapping audit events, seed data, and factories.

## Accounting policies

- Reports read `journal_entries.status = posted` only.
- Draft, pending, approved, cancelled, and failed sources never affect financial statements.
- A reversed original remains posted and is linked to its separately posted reversal. Both remain visible and their net effect is zero.
- Opening is activity before `date_from`; movement is activity from `date_from` through `date_to`; closing is opening plus movement.
- Base amounts are authoritative for Trial Balance and financial statements.
- Header balances are calculated from posting descendants. Grand totals are calculated from posting rows only, preventing parent/child double counting.
- Adjusted view includes posted adjusting journals. Unadjusted view excludes journals where `is_adjusting = true`.
- Current profit is presentation-only in the Balance Sheet. No retained-earnings transfer or period/year closing was added.
- No account balance is stored; balances are always derived from posted journal lines.

## Security and isolation

- All reporting routes are inside the authenticated tenant route group.
- Every report requires its dedicated permission and applies company and accessible-branch scope.
- Requests prohibit client `company_id`, raw SQL, and formulas; dimension IDs are validated against the active company.
- Report definitions and mappings use policy checks and audited changes.
- General Ledger and Trial Balance exports require export permissions and create an audit record.
- CSV cells beginning with `=`, `+`, `-`, or `@` are forced to safe text.

## Persistence and performance

Migration `2026_07_26_050000_create_financial_reporting_foundation.php` adds:

- `financial_report_definitions`
- `financial_report_sections`
- `financial_report_account_mappings`
- `cash_flow_mappings`
- reporting indexes for journal dates, periods, sources, accounts, branches, cost centers, parties, products, warehouses, and currencies

It does not add stored balances or commercial documents. The migration was applied on development and rollback/reapply was verified on `laravel_test_project_testing`.

## Routes

- `/accounting/reports/general-ledger`
- `/accounting/reports/general-journal`
- `/accounting/reports/trial-balance`
- `/accounting/reports/income-statement`
- `/accounting/reports/balance-sheet`
- `/accounting/reports/cash-flow`
- `/accounting/reports/cost-centers`
- `/accounting/reports/branches`
- `/accounting/reports/reconciliation`
- `/accounting/reports/unposted-sources`
- `/accounting/reports/definitions`

## Verification

- Full suite: `173 passed`.
- Phase 14 tests: `25 passed`; Phase 14C: `8 passed`.
- Pint: `936 files passed`.
- Composer validation: valid.
- Vite: successful build, with the pre-existing unresolved static website asset warnings.
- Blade cache: successful.
- Seeder is idempotent and creates no journal entry.
