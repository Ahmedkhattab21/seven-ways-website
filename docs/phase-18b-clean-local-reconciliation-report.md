# Phase 18B — Clean Local Reconciliation Report

Date: 2026-07-28

## 1. Target Verification

| Item | Result |
| --- | --- |
| Host | `127.0.0.1` |
| Port | `3307` |
| Database | `seven_ways_clean_local` |
| Environment | `local` |
| PHP | `8.2.12` |
| MariaDB | `10.4.32-MariaDB` |
| Original historical DB touched | No |
| Port 3306 used | No |
| Testing DB rebuilt/deleted | No |

The target identity was verified before the destructive operation. The delete
guard required an exact, case-sensitive match with
`seven_ways_clean_local`.

## 2. Pre-rebuild Counts

| Table/domain | Count | Operational data? |
| --- | ---: | --- |
| companies | 1 | QA tenant only |
| users | 7 | QA users only |
| branches | 3 | Main/QA branches only |
| journal_entries | 0 | No |
| sales_invoices | 0 | No |
| customer_payments | 0 | No |
| sales_credit_notes | 0 | No |
| purchase_requisitions | 0 | No |
| purchase_orders | 0 | No |
| supplier_invoices | 0 | No |
| supplier_payments | 0 | No |
| treasury_transfers | 0 | No |
| cash_receipts | 0 | No |
| cash_payments | 0 | No |
| merchant_settlements | 0 | No |
| employee_commission_accruals | 0 | No |
| employee_expense_claims | Table missing in partial schema | No |
| employee_advances | Table missing in partial schema | No |
| approval_tasks | 0 | No |
| system_notifications | 0 | No |
| audit_events | 0 | No |

All seven users used `@sevenways.test` QA identities. No invoices, journals,
treasury operations, employee-finance documents, workflow records, or other
business history existed. Rebuild was therefore permitted.

## 3. Backup

| Item | Result |
| --- | --- |
| Path | `C:\seven-ways-backups\clean-local\seven_ways_clean_local-20260728-002955.sql` |
| Size | 658,958 bytes |
| Non-empty | Yes |
| Table/database definitions found | Yes |
| Added to Git | No |

The logical dump was created before deletion and can restore the disposable
pre-rebuild state if required.

## 4. Rebuild

Only the exact database `seven_ways_clean_local` was dropped using the isolated
MariaDB TCP connection on port 3307. It was recreated with character set
`utf8mb4` and collation `utf8mb4_unicode_ci`; the initial table count was zero.

`seven_ways_testing` was verified before and after the operation and was never
selected by the drop command. No files under `D:\xxamp\mysql\data` were read,
changed, or deleted.

## 5. Migrations

`optimize:clear`, the empty-state status check, SQL preview, migration run, and
final status check completed on clean-local. All 196 tables were created and no
migration remained pending.

| Phase | Migration | Status |
| --- | --- | --- |
| Phase 16 | `2026_07_27_000000_set_egypt_company_column_defaults` | Ran |
| Phase 17 | `2026_07_27_100000_create_employee_finance_tables` | Ran |
| Phase 17 | `2026_07_27_110000_add_employee_finance_line_uuids` | Ran |
| Phase 18 | `2026_07_27_120000_create_central_workflow_tables` | Ran |

No duplicate-table or missing-table error occurred from a clean start.

## 6. Seeders

The base seeder completed, then `EmployeeFinanceSeeder`,
`CentralWorkflowSeeder`, and `TreasuryManualQaSeeder` were each run repeatedly.
Employee Finance was run again after Treasury QA created the first clean-local
actor, allowing its company-specific reference categories to be created.

Stable idempotency signature before/after the final repetition:

```text
permissions|roles|users|branches|accounts|expense categories|workflows|
workflow steps|approval limits|sequences|banks|bank accounts|cash boxes|payment methods
534|14|7|3|46|4|3|3|23|69|2|2|3|8
```

Duplicate groups were zero for permissions, roles, currencies, users, branches,
expense categories, workflows, sequences, approval limits, banks, bank
accounts, cash boxes, and payment mappings.

The combined count remained zero for journals, treasury documents, cash
sessions, employee-finance documents, approval tasks, notifications,
delegations, audit events, and stored stock balances.

## 7. Egypt Audit

The read-only command `localization:audit-egypt` completed successfully:

| Setting/check | Result |
| --- | --- |
| Country | `EG` |
| Base currency | `EGP` |
| Timezone | `Africa/Cairo` |
| Default language | `ar` |
| Posted documents | 0 |
| Posted SAR documents | 0 |
| Posted SAR journals | 0 |
| Opening balances | 0 |
| VAT 15 historical lines | 0 |
| Currencies retained | AED, EGP, SAR, USD |
| Egyptian tax reference | `VAT14-EG` at 14%, configurable |

`--apply-safe-defaults` was not used. A remaining Saudi `.sa` email placeholder
found during HTTP smoke inspection was changed to a neutral `.com` example and
covered by a static localization assertion.

## 8. Tests and quality checks

| Command/check | Passed | Failed | Notes |
| --- | ---: | ---: | --- |
| Clean-local authenticated HTTP smoke | 8 | 0 | Login plus seven protected pages returned 200 |
| `test --filter=PhaseEighteen` | 12 | 0 | Testing DB |
| `test --filter=PhaseSeventeen` | 8 | 0 | Testing DB |
| `test --filter=PhaseFifteen` | 31 | 0 | Testing DB |
| `test --filter=EgyptLocalization` | 9 | 0 | Testing DB |
| `test --filter=TreasuryManualQa` | 6 | 0 | Testing DB |
| Full `artisan test` | 264 | 0 | 264 passed |
| Pint | 1,355 files | 0 | Passed |
| Composer validate | 1 | 0 | `composer.json` valid |
| Vite build | 60 modules | 0 | Built successfully |
| View cache | 1 | 0 | Cached successfully |
| Route list | 509 routes | 0 | Passed |
| Schedule list | 6 tasks | 0 | Passed |
| `git diff --check` | 1 | 0 | Passed |

The Vite build emitted non-fatal runtime-resolution warnings for website fonts
and images. Every referenced file was verified to exist under
`public/assets/website`.

Clean-local smoke results:

```text
login -> /dashboard: 200
/dashboard: 200
/settings/company: 200
/treasury: 200
/employee-finance: 200
/approvals: 200
/notifications: 200
/audit: 200
```

## 9. Schema Comparison

Both clean-local and testing contain 196 tables. All Phase 17 and Phase 18
tables exist in both databases. Index and foreign-key fingerprints for the
Phase 17/18 tables are identical:

```text
indexes:
f0e6b7403f48b76d93bd2437b2bb70cfc2fd57e1899acaf8ac9f254ed589fae6

foreign keys:
dfb891797ed698b61ded6ce0c3565a2281f350f14788c6e3eafd2a44d76f9750
```

There are exactly two column-signature differences:

| Table | Column | clean-local | testing |
| --- | --- | --- | --- |
| employee_commission_settlement_lines | uuid | `char(36) NOT NULL` | `char(36) NULL` |
| employee_expense_claim_items | uuid | `char(36) NOT NULL` | `char(36) NULL` |

The clean build receives these UUIDs from the Phase 17 table-creation migration
as required fields. The older testing schema received them later through the
forward-compatible additive migration, which intentionally used `nullable()`.
Both have identical unique indexes, and the complete suite passed. Testing was
not modified, as required.

Clean-local has the complete seeded references (534 permissions, 3 workflows,
4 expense categories, 4 currencies, 1 tax). The persistent testing baseline
has 494 permissions, no workflow/category seed rows, 4 currencies, and 1 tax;
tests create their scoped reference fixtures transactionally. Operational test
rows were intentionally not compared.

## 10. Root Cause

The former clean-local state had the first Phase 17 table while the migration
was still pending. MariaDB DDL auto-commits, so an interrupted PHP/MariaDB
process can retain an early `CREATE TABLE` while Laravel never records the
migration batch. A retry then encounters the retained table.

A fully empty rebuild executed every migration successfully. This is evidence
of a prior interrupted migration, not a reproducible Phase 17 migration defect.
No historical migration was edited and no reconciliation migration was
created. The runbook documents the safe disposable-database recovery path.

## 11. Remaining Risks

| Severity | Evidence | Required action | Blocks Phase 19? |
| --- | --- | --- | --- |
| Low | Two historical testing UUID columns are nullable; unique indexes and all tests pass | Keep documented; address only through an approved future forward migration if business data requires strict parity | No |
| Low | Vite reports runtime asset resolution warnings; all referenced public files exist | Preserve public asset deployment paths | No |

No Critical or High unresolved risk remains.

## Readiness Decision

**GO — Ready for Phase 19**

Phase 19 was not started.
