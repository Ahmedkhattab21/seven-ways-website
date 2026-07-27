# Phase 18 — Central Approvals, Notifications, Delegation and Unified Audit

Date: 2026-07-27  
Runtime: PHP 8.2.12, Laravel 9, MariaDB 10.4.32  
Databases: `seven_ways_clean_local` and `seven_ways_testing` on `127.0.0.1:3307` only.

## 1. Scope and executive summary

Phase 18 adds a central, tenant-scoped approval inbox without replacing document state or accounting rules. Decisions are delegated to the original module services through typed handlers. It also adds secure temporary delegation, idempotent in-app notifications, correlation IDs, an append-only unified audit, scheduled generators, RTL pages, reports, permissions, factories, seed data, and automated tests.

The historical MariaDB data directory on port 3306 was not queried, migrated, seeded, repaired, or changed. ETA, ZATCA, government integration, email delivery, stored balances, journal generation, and historical data conversion are outside this phase.

## 2. Existing approval-flow audit

The repository already used module-specific state machines, policies, `TenantContext`, module/accounting locks, service transactions, and legacy `audit_logs`. It did not have a central inbox, database notification center, delegation model, request correlation middleware, or an audit record carrying effective actor and correlation ID.

| Module | Model | Pending status | Approval method | Reject method | Service | Permission | Limit service | SOD |
|---|---|---|---|---|---|---|---|---|
| Sales | `Quotation` | `pending_approval` | `approve` | `reject` | `QuotationApprovalService` | `quotations.approve/reject` | Module rules | Service/policy |
| Sales | `SalesInvoice` | `pending_approval` | `approve` | None | `SalesInvoiceApprovalService` | `sales_invoices.approve` | Module rules | Service |
| Sales | `SalesCreditNote` | workflow status | `approve` | None | `SalesCreditNoteService` | `sales_credit_notes.approve` | Module rules | Service |
| Receivables | `CustomerPayment` | workflow status | `approve` | None | `CustomerPaymentService` | policy permission | Module rules | Service |
| Receivables | `CustomerRefund` | workflow status | `approve` | None | `CustomerRefundService` | policy permission | Module rules | Service |
| Purchasing | `PurchaseRequisition` | `pending_approval` | `approve` | `reject` | `PurchaseRequisitionApprovalService` | `purchase_requisitions.approve/reject` | Existing module rules | Yes |
| Purchasing | `PurchaseOrder` | `pending_approval` | `approve` | None | `PurchaseOrderApprovalService` | `purchase_orders.approve` | Existing module rules | Yes |
| Purchasing | `SupplierInvoice` | `pending_approval` | `approve` | None | `SupplierInvoiceApprovalService` | `supplier_invoices.approve` | Matching rules | Service |
| Purchasing | `SupplierPayment` | workflow status | `approve` | None | `SupplierPaymentService` | policy permission | Module rules | Service |
| Purchasing | `SupplierCreditNote` | workflow status | `approve` | None | `SupplierCreditNoteService` | policy permission | Module rules | Service |
| Inventory | `StockTransfer` | submitted workflow | `approve` | `reject` | `StockTransferApprovalService` | `stock_transfers.approve` | Stock rules | Service |
| Inventory | `InventoryCount` | counted workflow | `post` | None | `InventoryCountService` | policy permission | Module lock | Service |
| Accounting | `JournalEntry` | `submitted` | `approve/post` | cancel only | `JournalEntryService` / posting service | journal permissions | Period/module locks | Yes |
| Accounting | `OpeningBalanceDocument` | submitted workflow | `approve/post` | None | `OpeningBalanceService` | policy permission | Period/module locks | Yes |
| Accounting | `AccountingClosingRun` | review workflow | `review/approve` | None | closing/year-end services | closing permissions | Period/module locks | Multi-actor |
| Banking | `BankReconciliationSession` | review workflow | `review/approve` | match rejection only | reconciliation services | policy permission | Module lock | Yes |
| Treasury | `TreasuryTransfer` | `pending_approval` | `action(..., approve)` | None | `TreasuryTransferService` | `treasury.transfers.approve` | `TreasuryApprovalLimitService` | Yes |
| Treasury | cash receipt/payment/session/count | submitted workflow | `action(..., approve)` | cancel only | cash operation/session services | treasury permissions | `TreasuryApprovalLimitService` | Yes |
| Treasury | cheque/merchant settlement | submitted workflow | `action(..., approve)` | return/cancel paths | cheque/merchant services | treasury permissions | `TreasuryApprovalLimitService` | Yes |
| Employee finance | commission/expense/advance | module-specific pending status | module action `approve` | expense reject | `EmployeeFinanceService` | employee-finance permissions | Existing employee rules | Yes |

Central handlers were enabled only where integration could be made without changing the module state machine: purchase requisitions, purchase orders, and treasury transfers. The remaining flows stay authoritative in their current services and are deferred for typed handlers.

## 3. Architecture

### Central approval

`approval_tasks` stores workflow metadata and a morph reference; it does not copy line items or become the business source of truth. The idempotency key is derived server-side from document type, ID, stage, and version timestamp. A task records only a display amount snapshot.

`CentralApprovalService`:

1. locks the task and original document;
2. verifies company, branch, source state, permission/delegation, SOD, and limits;
3. calls the registered module handler;
4. the handler calls the original module service;
5. appends the task action and unified audit;
6. queues result notification creation with `DB::afterCommit`.

It never posts journals or writes the source document status directly.

### Handlers and workflow versioning

`ApprovalModuleRegistry` resolves:

| Module | Document | Submit event | Approve service | Reject | Central task |
|---|---|---|---|---|---|
| Purchasing | Purchase requisition | `PurchaseRequisitionSubmitted` | `PurchaseRequisitionApprovalService` | Yes, original service | Yes |
| Purchasing | Purchase order | `PurchaseOrderSubmitted` | `PurchaseOrderApprovalService` | No | Yes |
| Treasury | Treasury transfer | `TreasuryTransferSubmitted` | `TreasuryTransferService` | No | Yes |

The task resolves the most specific active company/branch workflow and stores its workflow and step IDs. Running tasks therefore keep the selected version. The current integration is intentionally single-step because invoking the module approval before its final step would bypass the existing module state machine. Multi-step tables are ready, but multi-step execution is deferred per module.

### Delegation

Delegations are date-bounded, module-scoped, optionally branch-scoped, same-company, and do not alter roles. Both users must be active and branch-authorized. Circular chains and overlapping duplicate delegations are rejected. Central authorization rechecks that the delegator still owns the document permission. For treasury, both delegator and delegate limits must pass, implementing the effective lower-limit rule without FX assumptions.

### Notifications

`system_notifications` is the primary in-app channel. Keys are event/user/source/version based and unique. Metadata has an allowlist, messages are HTML-stripped and length-limited, and only internal relative action URLs are accepted. Read and mark-all-read queries are user and company scoped. Email remains disabled/not required.

Implemented types: new approval, approval result, approval overdue, delegation expired, old open cash session, and outstanding employee advance.

### Unified audit and correlation

The legacy `audit_logs` table is retained for compatibility. It was not suitable for the new security contract because it lacks immutable model guards, UUID, effective actor, delegation, old/new field sets, and correlation ID. `audit_events` is the new append-only security/approval trail; there is no update/delete controller or delete route.

Masked keys: `password`, `password_confirmation`, `token`, `api_token`, `secret`, `account_number`, `iban`, `cheque_number`, `card_number`, and `attachment_path`. Request bodies/files/Base64 are never copied. `AssignCorrelationId` creates a UUID, adds it to log context, audit, and `X-Correlation-ID`.

## 4. Database

Migration: `2026_07_27_120000_create_central_workflow_tables.php`.

Tables:

- `approval_workflows`
- `approval_workflow_steps`
- `approval_delegations`
- `approval_tasks`
- `approval_task_actions`
- `system_notifications`
- `audit_events`

All foreign keys use restrictive deletion. Operational history has no cascade delete. Index names are explicitly short where composite names could be long. The migration is forward-only; `down()` intentionally does not destroy workflow/audit history. No old migration was edited and no old operational row was backfilled.

## 5. Permissions and role mapping

Added:

- `approvals.view`, `approvals.act`, `approvals.manage_workflows`, `approvals.view_all_branches`
- `notifications.view`, `notifications.generate`
- `audit.view`, `audit.view_sensitive`, `audit.export`
- `delegations.view`, `delegations.create`, `delegations.cancel`

Owner/system administrator receive administrative capabilities. General manager receives central action and operational audit. Branch manager is branch-scoped. Accountant receives inbox/action entry points but gains no module approval permission; the original module permission is still required. No viewer receives action permission. No real user role membership is changed.

## 6. UI, routes, and reports

Authenticated RTL pages:

- `/approvals` — scoped inbox and filters
- `/approvals/{approval}` — safe details, timeline, approve/reject when supported
- `/delegations` — list/create/cancel
- `/notifications` — own notifications and read actions
- `/audit` — scoped immutable audit search
- `/approval-reports` — status/module aging, delegated decisions, and notification summary

Sidebar links are permission-controlled. Topbar shows the current user's unread count. Backend checks, not button visibility, are authoritative.

## 7. Scheduler

| Command | Frequency | Behavior |
|---|---|---|
| `approvals:mark-overdue` | Hourly | Idempotent overdue notification; does not alter source status |
| `delegations:expire` | Hourly | Expires ended delegation and audits/notifies once |
| `notifications:generate-operational` | Daily 07:00 | Old cash sessions and outstanding advances, tenant/user scoped |

All generators chunk queries and exclude irrelevant source statuses.

## 8. Seeder and factories

`CentralWorkflowSeeder` is idempotent. It creates permissions, safe system-role mappings, three single-step workflow defaults, and no tasks, notifications, delegations, audit events, journals, balances, or documents. Two direct runs completed successfully.

Factories were added for workflows, steps, tasks, task actions, delegations, notifications, and audit events. They do not hardcode company, branch, user, or currency IDs.

## 9. Automated tests

Test classes:

- `PhaseEighteenCentralWorkflowTest`
- `PhaseEighteenNotificationsAuditTest`

Covered: schema/no stored balance, task and notification idempotency, original-service dispatch, SOD, one decision only, required rejection reason, source rejection path, company/branch URL isolation, delegation cycles, seeder safety, no audit delete route, safe notification URLs/metadata, user-only read actions, audit masking/immutability, and correlation headers.

| Command | Passed | Failed | Notes |
|---|---:|---:|---|
| `artisan test --filter=PhaseEighteen` | 12 | 0 | Final run |
| `artisan test --filter=PhaseSeventeen` | 8 | 0 | Regression |
| `artisan test --filter=PhaseFifteen` | 31 | 0 | Regression |
| `artisan test --filter=EgyptLocalization` | 9 | 0 | Regression |
| `artisan test --filter=TreasuryManualQa` | 6 | 0 | Regression |
| `artisan test` | 264 | 0 | Final full suite |
| `vendor/bin/pint --test` | 1355 files | 0 | Passed |
| `composer validate` | 1 | 0 | `composer.json` valid |
| `npm.cmd run build` | 1 | 0 | Passed; existing runtime asset-resolution warnings remain |
| `artisan view:cache` | 1 | 0 | Passed |
| `artisan route:list` | 509 routes | 0 | Passed |
| `artisan schedule:list` | 6 entries | 0 | Includes 3 new jobs |
| `git diff --check` | 1 | 0 | Passed; Git reports CRLF conversion notices only |
| `migrate --pretend` on clean local | 1 | 0 | Phase 18 SQL generated safely |
| targeted Phase 18 `migrate --force` on clean local | 1 | 0 | Phase 18 migration ran |
| `migrate --force` on testing | 1 | 0 | Phase 18 migration ran |
| full `migrate --force` on clean local | 0 | 1 | Blocked by pre-existing partial Phase 17 schema |
| full `db:seed --force` on clean local | 0 | 1 | Blocked by missing Phase 17 table |
| `CentralWorkflowSeeder` twice | 2 | 0 | Passed, idempotent |

## 10. Risks and deferred items

### Critical — clean-local Phase 17 migration ledger/schema mismatch

Evidence:

- `migrate:status` reports Phase 17 migrations `100000` and `110000` as pending in `seven_ways_clean_local`.
- `employee_commission_rules` already exists, so the Phase 17 migration cannot start.
- `employee_expense_categories` does not exist, so the full database seeder stops at `EmployeeFinanceSeeder`.
- `seven_ways_testing` reports all migrations through Phase 18 as ran and all tests pass.

Required action: take a backup of the isolated clean-local database, compare every Phase 17 table/column/index against its migrations, then create an explicit forward-only reconciliation migration or rebuild only that disposable isolated database after approval. Do not edit migration history blindly. This issue blocks a Phase 19 GO decision, but does not concern the historical port-3306 database.

### Medium — module coverage

Only three document types are connected to central decisions. The audit inventory above identifies the next handlers. Adding them requires tests proving their original limits, locks, posting, and SOD continue to run.

### Medium — multi-level execution

Versioned workflow/step storage and selection are implemented, but source-service execution remains single-step. Per-module intermediate approval semantics are deferred to avoid early source approval or duplicated business logic.

### Low — Vite warnings

The build passes, but existing website font/image URLs remain unresolved at build time and are expected to resolve at runtime.

## 11. Phase 18 files

Modified integration files:

- `app/Console/Kernel.php` — scheduler entries
- `app/Http/Kernel.php` — correlation middleware
- `app/Providers/EventServiceProvider.php` — central task listeners
- `app/Services/PurchaseOrderApprovalService.php` — emits submitted event after commit
- `database/seeders/DatabaseSeeder.php` — central seeder
- `resources/views/partials/sidebar.blade.php` — central navigation
- `resources/views/partials/topbar.blade.php` — unread notifications
- `routes/web.php` — authenticated central routes

New files are the Phase 18 migration, seven models, seven factories, `CentralWorkflowSeeder`, three commands, correlation middleware, event/listeners, central approval handlers/services, four central controllers plus report controller, five RTL view groups/pages, two test classes, and this report.

Unrelated concurrent public-website registration changes visible in the working tree were preserved and are not part of Phase 18.

## 12. Readiness decision

**NO-GO — Phase 19 is blocked**

Phase 18 code and the complete testing suite pass, but the required full migration and full seeder cannot complete on `seven_ways_clean_local` because of the pre-existing partial Phase 17 schema/ledger mismatch. The historical database remains untouched.
