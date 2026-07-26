# Phase 14B — Journal Engine and Automatic Posting

## Scope delivered

- Double-entry journal tables, immutable posted entries, permanent posting links, payment-method mappings, and product mappings.
- Manual workflow: `draft -> pending_approval -> approved -> posted`, with cancellation and separation of duties.
- Explicit Preview/Post/Retry/Reverse flows. Preview performs no writes.
- Period resolution rejects missing, overlapping, closed, locked, or module-locked periods. Soft-closed posting needs setting, permission, and reason.
- Idempotency is enforced by both source/action and idempotency-key unique constraints.
- Reversal creates one exact opposite journal; the original journal and operational source remain preserved.
- Account balances are not stored on `accounts`.

## Posting policy

| Source | Debit | Credit | Cost authority |
|---|---|---|---|
| Sales invoice | Accounts receivable | Revenue + VAT output | No COGS here |
| Sales credit note | Sales returns + VAT reversal | Accounts receivable | No inventory duplication |
| Customer payment | Payment-method account | Customer advances | Payment source |
| Customer refund | Customer advances | Payment-method account | Refund source |
| Supplier invoice | GRNI / purchase clearing + VAT input | Accounts payable | No stock here |
| Supplier credit note | Accounts payable | Purchase-return clearing + VAT reversal | No stock here |
| Supplier payment | Supplier advances | Payment-method account | Payment source |
| Goods receipt | Inventory | GRNI | `stock_movements.total_cost` |
| Purchase return | Purchase-return clearing | Inventory | `stock_movements.total_cost` |
| Stock movement consumption/sale | COGS | Inventory | The stock movement itself |
| Inventory adjustment gain/loss | Inventory or adjustment | Adjustment or inventory | The stock movement itself |
| Opening balance | Exact approved opening lines | Exact approved opening lines | Opening document lines |

Direct-sale COGS, work-order material/roll/scrap consumption, and returns are posted from the authoritative stock movement only. This prevents double deduction and double cost.

## Stock transfer decision

When source and destination resolve to the same inventory account, the transfer creates a permanent `not_required` posting link and no journal. A transfer between different inventory accounts is rejected until an explicit inter-account transfer policy is configured.

## Safety and authorization

- Company and accessible-branch scope is enforced.
- Manual control-account posting needs `accounting.journals.post_control_accounts`.
- Client input cannot set company, journal number, status, totals, source identity, period, actors, or reversal fields.
- Posted journals and posting links cannot be deleted.
- Mapping endpoints validate branch, product, payment method, and account ownership.
- Source posting is manual; existing auto-post settings remain disabled by default.

## Migration verification

Migration `2026_07_26_040000_create_journal_engine_tables.php` was applied in development. Its rollback and re-application were verified against `laravel_test_project_testing` only. The initial development attempt hit MariaDB's identifier-length limit; the partial empty Phase 14B tables were removed and the unique-index name was shortened before a successful re-run.

## Tests

`PhaseFourteenJournalEngineTest` covers schema, no stored balances, idempotent seeding, non-resetting sequences, double-entry validation, manual status transitions, control accounts, opening posting/reversal, duplicate-post prevention, no invoice COGS duplication, stock-transfer no-journal policy, and closed-period rejection.

Full suite result: **165 passed**.

## Deferred

General Ledger reports, Trial Balance, financial statements, cash flow, closing entries, bank reconciliation, ZATCA, budgets, fixed assets, payroll, FX revaluation, and consolidation remain outside Phase 14B.
